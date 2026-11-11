<?php

declare(strict_types=1);

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertSoftDeleted;

/*
 * |--------------------------------------------------------------------------
 * | Profile Overview Tests
 * |--------------------------------------------------------------------------
 */

it('displays profile overview page for admin', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.settings.profile.overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/overview')
            ->has('user'));
});

/*
 * |--------------------------------------------------------------------------
 * | Profile Edit Tests
 * |--------------------------------------------------------------------------
 */

it('displays profile edit page for admin', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.settings.profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/edit')
            ->has('user')
            ->has('mustVerifyEmail'));
});

/*
 * |--------------------------------------------------------------------------
 * | Profile Update Tests
 * |--------------------------------------------------------------------------
 */

it('updates user profile', function () {
    $admin = adminUser();

    actingAs($admin)
        ->patch(route('admin.settings.profile.update'), [
            'name' => 'Updated Name',
            'email' => $admin->email,
            'phone' => '1234567890',
            'bio' => 'Updated bio',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Profile updated successfully.');

    expect($admin->fresh()->name)->toBe('Updated Name');
    expect($admin->fresh()->phone)->toBe('1234567890');
});

it('resets email verification when email is changed', function () {
    $admin = adminUser();
    $originalVerifiedAt = $admin->email_verified_at;

    actingAs($admin)
        ->patch(route('admin.settings.profile.update'), [
            'name' => $admin->name,
            'email' => 'newemail@example.com',
        ])
        ->assertRedirect();

    expect($admin->fresh()->email)->toBe('newemail@example.com');
    expect($admin->fresh()->email_verified_at)->toBeNull();
});

it('validates profile update data', function () {
    $admin = adminUser();

    actingAs($admin)
        ->patch(route('admin.settings.profile.update'), [
            'name' => '',
            'email' => 'invalid-email',
        ])
        ->assertSessionHasErrors(['name', 'email']);
});

it('updates social links', function () {
    $admin = adminUser();

    $socialLinks = [
        'twitter' => 'https://twitter.com/test',
        'linkedin' => 'https://linkedin.com/in/test',
    ];

    actingAs($admin)
        ->patch(route('admin.settings.profile.update'), [
            'name' => $admin->name,
            'email' => $admin->email,
            'social_links' => $socialLinks,
        ])
        ->assertRedirect();

    expect($admin->fresh()->social_links)->toBe($socialLinks);
});

/*
 * |--------------------------------------------------------------------------
 * | Account Deletion Tests
 * |--------------------------------------------------------------------------
 */

it('displays account deletion page', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.settings.profile.delete'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/delete')
            ->has('user'));
});

it('schedules account for deletion', function () {
    $admin = adminUser();

    actingAs($admin)
        ->post(route('admin.settings.profile.delete.schedule'), [
            'password' => 'password',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Your account has been scheduled for deletion in 30 days.');

    expect($admin->fresh()->scheduled_deletion_at)->not->toBeNull();
});

it('requires password to schedule deletion', function () {
    $admin = adminUser();

    actingAs($admin)
        ->post(route('admin.settings.profile.delete.schedule'), [
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors(['password']);
});

it('cancels scheduled account deletion', function () {
    $admin = adminUser();
    $admin->scheduled_deletion_at = now()->addDays(30);
    $admin->save();

    actingAs($admin)
        ->post(route('admin.settings.profile.delete.cancel'))
        ->assertRedirect()
        ->assertSessionHas('status', 'Account deletion has been cancelled.');

    expect($admin->fresh()->scheduled_deletion_at)->toBeNull();
});

it('deletes account immediately with password confirmation', function () {
    $admin = adminUser();
    $adminId = $admin->id;

    actingAs($admin)
        ->delete(route('admin.settings.profile.destroy'), [
            'password' => 'password',
        ])
        ->assertRedirect('/');

    assertSoftDeleted('users', ['id' => $adminId]);
});

it('requires password to delete account immediately', function () {
    $admin = adminUser();

    actingAs($admin)
        ->delete(route('admin.settings.profile.destroy'), [
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors(['password']);
});
