<?php

declare(strict_types=1);

return [
    /**
     * Directories to scan for helper files.
     *
     * Each directory listed here will be scanned recursively for PHP files.
     * The files found in these directories will be included in the generated
     * include file or cached based on the command options.
     */
    'scan_directories' => [
        app_path('Helpers'),                 // Default directory for application-specific helpers
    ],

    /**
     * The name of the file that will be generated.
     *
     * This file will either contain 'require_once' statements for each helper file
     * or the combined content of all helper files if caching is enabled.
     */
    'generator_file_name' => 'helpers_generator.php',

    /**
     * The path where the generated include file will be stored.
     *
     * Ensure this directory exists and is writable. If it does not exist, it will
     * be created automatically.
     */
    'generator_file_path' => app_path('Helpers'),
];
