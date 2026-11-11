<?php

declare(strict_types=1);

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Contracts\User as SocialiteUser;

it('updates and deletes profile photos', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);

    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/old.jpg']);
    Storage::disk('public')->put('profile-photos/old.jpg', 'old');

    $file = UploadedFile::fake()->image('avatar.jpg');
    $user->updateProfilePhoto($file);

    expect($user->profile_photo_path)->not->toBeNull();
    expect(Storage::disk('public')->exists('profile-photos/old.jpg'))->toBeFalse();

    $user->deleteProfilePhoto();
    expect($user->profile_photo_path)->toBeNull();
});

it('returns default profile photo url when missing', function () {
    $user = User::factory()->make(['name' => 'Jane Doe', 'profile_photo_path' => null]);

    expect($user->profile_photo_url)->toContain('ui-avatars.com');
});

it('creates and updates social providers', function () {
    $user = User::factory()->create(['password' => null]);

    $socialiteUser = new class implements SocialiteUser
    {
        public function getId()
        {
            return '123';
        }

        public function getNickname()
        {
            return 'demo';
        }

        public function getName()
        {
            return 'Demo User';
        }

        public function getEmail()
        {
            return 'demo@example.com';
        }

        public function getAvatar()
        {
            return 'https://example.test/avatar.png';
        }
    };

    $account = $user->createOrUpdateProvider('github', $socialiteUser, 'token', 'refresh');
    expect($account->provider)->toBe('github');

    $updated = $user->createOrUpdateProvider('github', $socialiteUser, 'token-2', 'refresh-2');
    expect($updated->access_token)->toBe('token-2');
});

it('checks social-only and unlink behavior', function () {
    $user = User::factory()->create(['password' => null]);
    SocialAccount::factory()->create(['user_id' => $user->id, 'provider' => 'github']);

    expect($user->isSocialOnly())->toBeTrue();
    expect($user->canUnlinkProvider('github'))->toBeFalse();
    expect($user->unlinkProvider('github'))->toBeFalse();

    $user->update(['password' => 'password']);
    expect($user->canUnlinkProvider('github'))->toBeTrue();
    expect($user->unlinkProvider('github'))->toBeTrue();
});

it('returns linked providers metadata', function () {
    $user = User::factory()->create(['password' => 'secret']);
    SocialAccount::factory()->create(['user_id' => $user->id, 'provider' => 'github', 'metadata' => ['email' => 'demo@example.com']]);

    $linked = $user->getLinkedProviders();
    expect($linked)->toHaveCount(1);
    expect($linked[0]['provider'])->toBe('github');
});

it('updates user profile from social provider when missing', function () {
    // Provide valid initial data to pass validation/constraints
    $user = User::factory()->create(['name' => 'Placeholder', 'email' => 'placeholder@example.com']);

    // Manually fetch fresh instance or just modify attributes to simulate "empty" state for the trait check
    // The trait checks $this->name (in-memory attribute)
    $user->name = '';
    $user->email = '';

    $socialiteUser = new class implements SocialiteUser
    {
        public function getId()
        {
            return '555';
        }

        public function getNickname()
        {
            return 'demo';
        }

        public function getName()
        {
            return 'Demo Person';
        }

        public function getEmail()
        {
            return 'demo2@example.com';
        }

        public function getAvatar() {}
    };

    $user->updateFromSocialProvider($socialiteUser);
    $user->refresh();

    expect($user->name)->toBe('Demo Person');
    expect($user->email)->toBe('demo2@example.com');
});

it('manages user preferences via trait helpers', function () {
    $user = User::factory()->make(['preferences' => null]);

    $user->initializePreferences();
    expect($user->getPreference('appearance'))->toBe('light');

    $user->setPreference('appearance', 'dark');
    expect($user->getPreference('appearance'))->toBe('dark');

    $user->setPreferences(['notifications.email' => false, 'locale' => 'bn']);
    expect($user->getPreference('notifications.email'))->toBeFalse();
    expect($user->getPreference('locale'))->toBe('bn');
    expect($user->hasPreference('notifications.email'))->toBeTrue();
});
