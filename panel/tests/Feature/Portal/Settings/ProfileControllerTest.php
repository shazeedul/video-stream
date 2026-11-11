<?php

declare(strict_types=1);

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertSoftDeleted;

/*
 * |--------------------------------------------------------------------------
 * | Profile Overview Tests
 * |--------------------------------------------------------------------------
 */

it('displays profile overview page for portal user', function () {
    $user = portalUser();

    actingAs($user)
        ->get(route('portal.settings.profile.overview'))
        ->assertOk();
});

/*
 * |--------------------------------------------------------------------------
 * | Profile Edit Tests
 * |--------------------------------------------------------------------------
 */

it('displays profile edit page for portal user', function () {
    $user = portalUser();

    actingAs($user)
        ->get(route('portal.settings.profile.edit'))
        ->assertOk();
});

/*
 * |--------------------------------------------------------------------------
 * | Profile Update Tests
 * |--------------------------------------------------------------------------
 */

it('updates portal user profile', function () {
    $user = portalUser();

    actingAs($user)
        ->patch(route('portal.settings.profile.update'), [
            'name' => 'Updated Name',
            'email' => $user->email,
            'phone' => '1234567890',
        ])
        ->assertRedirect();

    expect($user->fresh()->name)->toBe('Updated Name');
});

it('validates profile update data', function () {
    $user = portalUser();

    actingAs($user)
        ->patch(route('portal.settings.profile.update'), [
            'name' => '',
            'email' => 'invalid-email',
        ])
        ->assertSessionHasErrors(['name', 'email']);
});

/*
 * |--------------------------------------------------------------------------
 * | Account Deletion Tests
 * |--------------------------------------------------------------------------
 */

it('displays account deletion page for portal user', function () {
    $user = portalUser();

    actingAs($user)
        ->get(route('portal.settings.profile.delete'))
        ->assertOk();
});

it('schedules portal user account for deletion', function () {
    $user = portalUser();

    actingAs($user)
        ->post(route('portal.settings.profile.delete.schedule'), [
            'password' => 'password',
        ])
        ->assertRedirect();

    expect($user->fresh()->scheduled_deletion_at)->not->toBeNull();
});

it('cancels scheduled account deletion for portal user', function () {
    $user = portalUser();
    $user->scheduled_deletion_at = now()->addDays(30);
    $user->save();

    actingAs($user)
        ->post(route('portal.settings.profile.delete.cancel'))
        ->assertRedirect();

    expect($user->fresh()->scheduled_deletion_at)->toBeNull();
});

it('deletes portal user account immediately', function () {
    $user = portalUser();
    $userId = $user->id;

    actingAs($user)
        ->delete(route('portal.settings.profile.destroy'), [
            'password' => 'password',
        ])
        ->assertRedirect('/');

    assertSoftDeleted('users', ['id' => $userId]);
});
