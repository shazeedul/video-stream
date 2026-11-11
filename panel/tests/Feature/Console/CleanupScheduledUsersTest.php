<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

it('displays scheduled users in dry run without deleting', function () {
    // create user scheduled for past
    $user = User::factory()->create([
        'scheduled_deletion_at' => now()->subDay(),
        'name' => 'To Delete',
    ]);

    // create user scheduled for future
    $futureUser = User::factory()->create([
        'scheduled_deletion_at' => now()->addDay(),
        'name' => 'Keep Me',
    ]);

    $this
        ->artisan('cleanup:users', ['--dry-run' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('Running in dry-run mode')
        ->expectsOutputToContain('Found 1 user(s) scheduled for deletion')
        ->expectsOutputToContain('To Delete')
        ->doesntExpectOutputToContain('Keep Me');

    assertDatabaseHas('users', ['id' => $user->id]);
});

it('deletes scheduled users and their photos', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'scheduled_deletion_at' => now()->subDay(),
        'profile_photo_path' => 'photos/user1.jpg',
    ]);

    Storage::disk('public')->put('photos/user1.jpg', 'content');

    // Mock Storage to ensure delete called.
    // Since we use Storage::delete($user->profile_photo_path) in command.
    // Command likely uses default disk?
    // Code says: Storage::delete(...). Uses default.
    // If default is not public, we should fake default.
    Storage::fake();
    Storage::put('photos/user1.jpg', 'content');

    $this
        ->artisan('cleanup:users')
        ->assertExitCode(0)
        ->expectsOutputToContain('Successfully deleted 1 of 1 user(s)');

    assertDatabaseMissing('users', ['id' => $user->id]);
    Storage::assertMissing('photos/user1.jpg');
});

it('reports no users to delete', function () {
    User::query()->update(['scheduled_deletion_at' => null]);

    $this
        ->artisan('cleanup:users')
        ->assertExitCode(0)
        ->expectsOutputToContain('No users found that need to be deleted');
});
