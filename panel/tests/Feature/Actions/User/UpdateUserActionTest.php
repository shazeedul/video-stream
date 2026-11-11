<?php

declare(strict_types=1);

use App\Actions\User\UpdateUserAction;
use App\Models\Role;
use App\Models\User;

it('updates user name and email', function () {
    $action = app(UpdateUserAction::class);
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'oldemail@example.com',
    ]);

    $updatedUser = $action->execute($user, [
        'name' => 'New Name',
        'email' => 'newemail@example.com',
    ]);

    expect($updatedUser->name)->toBe('New Name');
    expect($updatedUser->email)->toBe('newemail@example.com');
});

it('updates user password when provided', function () {
    $action = app(UpdateUserAction::class);
    $user = User::factory()->create();
    $oldPassword = $user->password;

    $action->execute($user, [
        'name' => $user->name,
        'email' => $user->email,
        'password' => 'newpassword123',
    ]);

    expect($user->fresh()->password)->not->toBe($oldPassword);
    expect(Illuminate\Support\Facades\Hash::check('newpassword123', $user->fresh()->password))->toBeTrue();
});

it('does not update password when empty', function () {
    $action = app(UpdateUserAction::class);
    $user = User::factory()->create();
    $originalPassword = $user->password;

    $action->execute($user, [
        'name' => 'Updated Name',
        'email' => $user->email,
        'password' => '',
    ]);

    expect($user->fresh()->password)->toBe($originalPassword);
});

it('syncs user roles', function () {
    $action = app(UpdateUserAction::class);
    $user = User::factory()->create();
    $adminRole = Role::where('name', 'admin')->first();
    $userRole = Role::where('name', 'user')->first();

    $user->assignRole($userRole);
    expect($user->roles)->toHaveCount(1);

    $action->execute($user, [
        'name' => $user->name,
        'email' => $user->email,
        'roles' => [$adminRole->id],
    ]);

    expect($user->fresh()->roles)->toHaveCount(1);
    expect($user->fresh()->hasRole('admin'))->toBeTrue();
    expect($user->fresh()->hasRole('user'))->toBeFalse();
});

it('removes all roles when roles array is empty', function () {
    $action = app(UpdateUserAction::class);
    $user = User::factory()->create();
    $userRole = Role::where('name', 'user')->first();

    $user->assignRole($userRole);
    expect($user->roles)->toHaveCount(1);

    $action->execute($user, [
        'name' => $user->name,
        'email' => $user->email,
        'roles' => [],
    ]);

    expect($user->fresh()->roles)->toHaveCount(0);
});
