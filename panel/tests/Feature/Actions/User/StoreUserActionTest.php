<?php

declare(strict_types=1);

use App\Actions\User\StoreUserAction;
use App\Models\Role;
use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;

it('creates a user with basic data', function () {
    $action = app(StoreUserAction::class);
    $role = Role::where('name', 'user')->first();

    $user = $action->execute([
        'name' => 'Test User',
        'email' => 'testuser@example.com',
        'password' => 'password123',
        'roles' => [$role->id],
    ]);

    expect($user)->toBeInstanceOf(User::class);
    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('testuser@example.com');
    expect($user->roles)->toHaveCount(1);

    assertDatabaseHas('users', [
        'name' => 'Test User',
        'email' => 'testuser@example.com',
    ]);
});

it('creates a user without roles', function () {
    $action = app(StoreUserAction::class);

    $user = $action->execute([
        'name' => 'No Role User',
        'email' => 'norole@example.com',
        'password' => 'password123',
    ]);

    expect($user)->toBeInstanceOf(User::class);
    expect($user->roles)->toHaveCount(0);
});

it('creates a user with multiple roles', function () {
    $action = app(StoreUserAction::class);
    $adminRole = Role::where('name', 'admin')->first();
    $userRole = Role::where('name', 'user')->first();

    $user = $action->execute([
        'name' => 'Multi Role User',
        'email' => 'multirole@example.com',
        'password' => 'password123',
        'roles' => [$adminRole->id, $userRole->id],
    ]);

    expect($user->roles)->toHaveCount(2);
});

it('hashes the password when creating user', function () {
    $action = app(StoreUserAction::class);

    $user = $action->execute([
        'name' => 'Hash Test User',
        'email' => 'hashtest@example.com',
        'password' => 'password123',
    ]);

    expect($user->password)->not->toBe('password123');
    expect(Illuminate\Support\Facades\Hash::check('password123', $user->password))->toBeTrue();
});
