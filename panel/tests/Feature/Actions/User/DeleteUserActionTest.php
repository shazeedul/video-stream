<?php

declare(strict_types=1);

use App\Actions\User\DeleteUserAction;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertSoftDeleted;

it('deletes a user', function () {
    $admin = adminUser();
    $targetUser = User::factory()->create();
    $targetUser->assignRole('user');

    actingAs($admin);

    $action = app(DeleteUserAction::class);
    $result = $action->execute($targetUser);

    expect($result)->toBeTrue();
    assertSoftDeleted('users', ['id' => $targetUser->id]);
});

it('removes roles before deleting user', function () {
    $admin = adminUser();
    $targetUser = User::factory()->create();
    $targetUser->assignRole('user');

    expect($targetUser->roles)->toHaveCount(1);

    actingAs($admin);

    $action = app(DeleteUserAction::class);
    $action->execute($targetUser);

    expect($targetUser->fresh()->roles)->toHaveCount(0);
});

it('throws exception when trying to delete own account', function () {
    $admin = adminUser();

    actingAs($admin);

    $action = app(DeleteUserAction::class);
    $action->execute($admin);
})->throws(Exception::class, 'Cannot delete your own account');
