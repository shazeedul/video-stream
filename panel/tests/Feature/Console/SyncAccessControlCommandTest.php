<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    //
});

it('syncs access control with no user interactions', function () {
    Permission::firstOrCreate(['name' => 'test_permission', 'guard_name' => 'web'], ['display_name' => 'Test Permission']);

    Artisan::call('auth:permissions-sync', ['--no-user' => true]);

    expect(Artisan::output())->toContain('Permissions and roles sync completed successfully!');
});

it('supports dry run for access control sync', function () {
    Artisan::call('auth:permissions-sync', ['--dry-run' => true]);

    expect(Artisan::output())->toContain('DRY RUN');
});

it('creates a new admin user from access control sync', function () {
    $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin', 'guard_name' => 'web', 'is_default' => true]);

    $this
        ->artisan('auth:permissions-sync')
        ->expectsConfirmation('Do you want to create a new admin user?', 'yes')
        ->expectsQuestion('Enter user name', 'Admin User')
        ->expectsQuestion('Enter user email', 'admin@example.com')
        ->expectsQuestion('Enter user password', 'password')
        ->expectsQuestion('Select roles for the user:', ['admin'])
        ->expectsConfirmation('Do you want to create another user?', 'no')
        ->assertExitCode(0);

    // Debug output if user creation fails
    if (User::where('email', 'admin@example.com')->count() === 0) {
        dump(Artisan::output());
    }

    $user = User::where('email', 'admin@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Admin User');
    expect($user->hasRole('admin'))->toBeTrue();
});

it('updates existing user during access control sync', function () {
    $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin', 'guard_name' => 'web', 'is_default' => true]);
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);

    $this
        ->artisan('auth:permissions-sync')
        ->expectsConfirmation('Do you want to create a new admin user?', 'yes')
        ->expectsQuestion('Enter user name', 'Existing User')
        ->expectsQuestion('Enter user email', 'existing@example.com')
        ->expectsQuestion('Enter user password', 'new-password')
        ->expectsConfirmation(sprintf("User with email '%s' already exists. Do you want to update their password and roles?", $existingUser->email), 'yes')
        ->expectsQuestion('Select roles for the user:', ['admin'])
        ->expectsConfirmation('Do you want to create another user?', 'no')
        ->assertExitCode(0);

    $existingUser->refresh();
    expect($existingUser->name)->toBe('Existing User');
    expect(Hash::check('new-password', $existingUser->password))->toBeTrue();
    expect($existingUser->hasRole('admin'))->toBeTrue();
});

it('validates user input during creation', function () {
    Role::firstOrCreate(['name' => 'admin'], ['guard_name' => 'web']);

    $this
        ->artisan('auth:permissions-sync')
        ->expectsConfirmation('Do you want to create a new admin user?', 'yes')
        ->expectsQuestion('Enter user name', '')  // Invalid
        ->expectsQuestion('Enter user email', 'invalid-email')  // Invalid
        ->expectsQuestion('Enter user password', 'short')  // Invalid
        // The command loops or errors out?
        // Based on code: it shows errors and returns false (terminating that loop iteration? No, createUser returns false).
        // Then loop asks "create another user?" -> No.
        ->expectsOutputToContain('The name field is required.')
        ->expectsOutputToContain('The email field must be a valid email address.')
        ->expectsOutputToContain('The password field must be at least 8 characters.')
        ->expectsConfirmation('Do you want to create another user?', 'no')
        ->assertExitCode(0);
});

it('aborts production sync without confirmation', function () {
    // Mock production environment
    app()->detectEnvironment(fn () => 'production');

    $this
        ->artisan('auth:permissions-sync')
        ->expectsConfirmation('This will sync permissions and roles in PRODUCTION. Are you sure?', 'no')
        ->expectsOutput('Sync cancelled.')
        ->assertExitCode(0);
});

it('runs detailed sync logs', function () {
    Artisan::call('auth:permissions-sync', ['--no-user' => true, '--detailed' => true]);
    expect(Artisan::output())->toContain('Sync Details:');
});
