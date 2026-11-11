<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],
        'dos' => [
            'driver' => 's3',
            'key' => env('DOS_ACCESS_KEY_ID'),
            'secret' => env('DOS_SECRET_ACCESS_KEY'),
            'region' => env('DOS_DEFAULT_REGION', 'sgp1'),
            'bucket' => env('DOS_BUCKET', 'amarseba'),
            'url' => mb_rtrim((string) env('DOS_URL', 'https://'.env('DOS_BUCKET', 'amarseba').'.'.env('DOS_DEFAULT_REGION', 'sgp1').'.cdn.digitaloceanspaces.com'), '/'),
            'endpoint' => mb_rtrim((string) env('DOS_ENDPOINT', 'https://'.env('DOS_DEFAULT_REGION', 'sgp1').'.cdn.digitaloceanspaces.com'), '/'),
            'use_path_style_endpoint' => env('DOS_USE_PATH_STYLE_ENDPOINT', false),
            'root' => env('DOS_DEFAULT_FOLDER_ROOT', 'amarseba'),
            'throw' => false,
            'asset_config' => [
                'root' => env('DOS_ASSET_FOLDER_ROOT', 'amarseba'),
                'url' => mb_rtrim((string) env('DOS_URL', 'https://'.env('DOS_BUCKET', 'amarseba').'.'.env('DOS_DEFAULT_REGION', 'sgp1').'.cdn.digitaloceanspaces.com'), '/')
                    .'/'.mb_trim((string) env('DOS_ASSET_FOLDER_ROOT', 'amarseba'), '/').'/',
                'directories' => [  // More descriptive key
                    'admin-assets' => resource_path('assets/admin-assets'),
                    'nanopkg-assets' => resource_path('assets/nanopkg-assets'),
                    'module-assets' => public_path('module-assets'),
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
