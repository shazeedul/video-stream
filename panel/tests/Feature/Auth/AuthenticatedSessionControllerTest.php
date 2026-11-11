<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

it('shows login page for guests', function () {
    test()->get(route('login'))->assertStatus(200);
});

it('logs in and logs out', function () {
    $password = 'password';
    $user = User::factory()->create(['password' => Hash::make($password), 'email_verified_at' => now()]);

    test()->post(route('login'), ['email' => $user->email, 'password' => $password])->assertRedirect();

    // Now logout
    actingAs($user)->post(route('logout'))->assertRedirect('/');
});
