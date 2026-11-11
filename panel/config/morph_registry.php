<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\SystemSetting;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

return [
    /*
    |--------------------------------------------------------------------------
    | Polymorphic Type Registry
    |--------------------------------------------------------------------------
    |
    | This configuration registry maps short polymorphic type names to their full
    | model class names. This allows you to use short names in your
    | polymorphic relationships instead of full namespaces.
    |
    | Example:
    | Instead of: 'Modules\VoterToNidNo\Entities\VoterToNidNo'
    | Use: 'VoterToNidNo'
    |
    */

    'registry' => [
        // System Models - Core system functionality (Users, Roles, Permissions etc.)
        'User' => User::class,
        'PersonalAccessToken' => PersonalAccessToken::class,
        'SystemSetting' => SystemSetting::class,
        'Permission' => Permission::class,
        'Role' => Role::class,
        'SocialAccount' => SocialAccount::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Strategy
    |--------------------------------------------------------------------------
    |
    | What to do when a polymorphic type is not found in the registry.
    | Options: 'exception', 'log', 'ignore'
    |
    */
    'fallback_strategy' => 'exception',

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Whether to cache the morph registry for better performance.
    | The cache will be cleared when the config is updated.
    |
    */
    'cache_enabled' => true,
    'cache_ttl' => 3600, // seconds
];
