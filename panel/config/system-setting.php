<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Group
    |--------------------------------------------------------------------------
    |
    | The default group for system settings when no group is specified.
    |
    */
    'default_group' => 'general',

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for system settings. Caching dramatically
    | improves performance by reducing database queries.
    |
    */
    'cache' => [
        // Whether caching is enabled
        'enabled' => env('SYSTEM_SETTING_CACHE', true),

        // Cache lifetime in minutes (default: 1 year)
        'lifetime' => env('SYSTEM_SETTING_CACHE_LIFETIME', 60 * 24 * 365),

        // Cache key prefix
        'prefix' => 'system_settings',

        // Cache keys for different query types
        'keys' => [
            'all_asc' => 'all_asc',
            'all_desc' => 'all_desc',
            'groups_asc' => 'groups_asc',
            'groups_desc' => 'groups_desc',
            'only_groups_asc' => 'only_groups_asc',
            'only_groups_desc' => 'only_groups_desc',
            'public' => 'public',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Detection Patterns
    |--------------------------------------------------------------------------
    |
    | Patterns to automatically detect encrypted and private settings based
    | on their key names. These are regex patterns.
    |
    */

    // Patterns to auto-detect encrypted settings (set is_encrypted=true)
    'encrypted_patterns' => [
        '/password$/i',
        '/secret$/i',
        '/_key$/i',
        '/token$/i',
        '/api[_-]?key$/i',
        '/private[_-]?key$/i',
        '/credential$/i',
    ],

    // Patterns to auto-set is_public=false (private settings)
    'private_patterns' => [
        '/password$/i',
        '/secret$/i',
        '/token$/i',
        '/credential$/i',
        '/private/i',
        '/_key$/i',
        '/api[_-]?key$/i',
    ],

];
