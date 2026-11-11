<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role as ModelsRole;

#[\Illuminate\Database\Eloquent\Attributes\Fillable([
    'name',
    'display_name',
    'guard_name',
    'description',
    'is_default',
])]
final class Role extends ModelsRole
{
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'boolean',
    ];
}
