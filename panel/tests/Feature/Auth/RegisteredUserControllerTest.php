<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function (): void {
    // Ensure the 'user' role exists for registration tests
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web'], ['display_name' => 'User']);
});

it('shows registration page for guests', function () {
    test()
        ->get(route('register'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('auth/register'));
});

it('registers a new user with valid data', function () {
    Event::fake([Registered::class]);

    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ];

    test()
        ->post(route('register'), $userData)
        ->assertRedirect(route('portal.dashboard'));

    assertDatabaseHas('users', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $user = User::query()->where('email', 'john@example.com')->first();
    expect(Hash::check('Password123!', $user->password))->toBeTrue();

    Event::assertDispatched(Registered::class);
});

it('validates required fields during registration', function () {
    test()
        ->post(route('register'), [])
        ->assertSessionHasErrors(['name', 'email', 'password']);
});

it('validates email format during registration', function () {
    test()->post(route('register'), [
        'name' => 'John Doe',
        'email' => 'invalid-email',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertSessionHasErrors(['email']);
});

it('validates email uniqueness during registration', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    test()->post(route('register'), [
        'name' => 'John Doe',
        'email' => 'existing@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertSessionHasErrors(['email']);
});

it('validates password confirmation during registration', function () {
    test()->post(route('register'), [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'DifferentPassword123!',
    ])->assertSessionHasErrors(['password']);
});

it('validates password strength during registration', function () {
    test()->post(route('register'), [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ])->assertSessionHasErrors(['password']);
});

it('converts email to lowercase during registration', function () {
    test()->post(route('register'), [
        'name' => 'John Doe',
        'email' => 'JOHN@EXAMPLE.COM',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    assertDatabaseHas('users', [
        'email' => 'john@example.com',
    ]);
});

it('logs in user after successful registration', function () {
    test()->post(route('register'), [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    test()->assertAuthenticated();
});
