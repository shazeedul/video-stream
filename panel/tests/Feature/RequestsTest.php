<?php

declare(strict_types=1);

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SetPasswordRequest;
use App\Http\Requests\Settings\UpdateSystemSettingsRequest;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

it('validates login request rules', function () {
    $request = new LoginRequest();

    expect($request->rules())->toHaveKey('email');
    expect($request->rules())->toHaveKey('password');
});

it('authenticates valid login credentials', function () {
    $password = 'secret-password';
    $user = User::factory()->create(['password' => Hash::make($password)]);

    $request = LoginRequest::create('/login', 'POST', [
        'email' => $user->email,
        'password' => $password,
        'remember' => true,
    ]);
    $request->setContainer(app())->setRedirector(app('redirect'));

    $request->authenticate();

    expect(RateLimiter::tooManyAttempts($request->throttleKey(), 5))->toBeFalse();
});

it('throws validation exception for invalid login', function () {
    $user = User::factory()->create(['password' => Hash::make('secret')]);

    $request = LoginRequest::create('/login', 'POST', [
        'email' => $user->email,
        'password' => 'wrong',
    ]);
    $request->setContainer(app())->setRedirector(app('redirect'));

    expect(fn () => $request->authenticate())->toThrow(ValidationException::class);
});

it('enforces rate limiting in login request', function () {
    $request = LoginRequest::create('/login', 'POST', [
        'email' => 'user@example.com',
    ], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
    $request->setContainer(app())->setRedirector(app('redirect'));

    RateLimiter::hit($request->throttleKey(), 60);
    RateLimiter::hit($request->throttleKey(), 60);
    RateLimiter::hit($request->throttleKey(), 60);
    RateLimiter::hit($request->throttleKey(), 60);
    RateLimiter::hit($request->throttleKey(), 60);

    expect(fn () => $request->ensureIsNotRateLimited())->toThrow(ValidationException::class);
});

it('returns set password rules and messages', function () {
    $request = new SetPasswordRequest();

    expect($request->rules())->toHaveKey('password');
    expect($request->messages())->toHaveKey('password.required');
    expect($request->messages())->toHaveKey('password.confirmed');
});

it('authorizes and prepares update system settings request', function () {
    $permission = Permission::firstOrCreate(['name' => 'system-settings.update', 'guard_name' => 'web']);
    $role = Role::factory()->create();
    $role->givePermissionTo($permission);

    $user = User::factory()->create();
    $user->assignRole($role);

    $setting = SystemSetting::factory()->create();

    $request = UpdateSystemSettingsRequest::create('/settings', 'POST', [
        'settings' => [
            [
                'id' => $setting->id,
                'group' => $setting->group,
                'name' => $setting->name,
                'value' => '••••••••',
                'type' => $setting->type->value,
                'is_public' => false,
            ],
        ],
    ]);

    $request->setUserResolver(fn () => $user);
    $request->setContainer(app())->setRedirector(app('redirect'));

    $request->validateResolved();

    $settings = $request->input('settings');
    expect($settings[0])->not->toHaveKey('value');
});
