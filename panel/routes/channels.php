<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', fn ($user, $id): bool => (int) $user->id === (int) $id);

Broadcast::channel('tenant.{tenantId}', function (User $user, string $tenantId): bool {
    return $user->tenants()->where('tenants.id', $tenantId)->exists();
});
