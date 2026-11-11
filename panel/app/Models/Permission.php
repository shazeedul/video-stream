<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Permission as ModelsPermission;

#[\Illuminate\Database\Eloquent\Attributes\Fillable([
    'name',
    'display_name',
    'group',
    'guard_name',
])]
final class Permission extends ModelsPermission
{
    use HasFactory;
    use Notifiable;
}
