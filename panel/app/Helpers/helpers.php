<?php

declare(strict_types=1);

use App\Facades\SystemSetting;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Check if an asset file should be versioned for cache busting.
if (! function_exists('should_version_asset')) {
    function should_version_asset(string $file): bool
    {
        static $cache = [];

        if (! isset($cache[$file])) {
            $extension = mb_strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $cache[$file] = in_array($extension, ['css', 'js'], true);
        }

        return $cache[$file];
    }
}

// Get the asset version for cache busting.
if (! function_exists('get_asset_version')) {
    function get_asset_version(?string $file = null): string
    {
        static $version = null;
        if ($version === null) {
            $version = config('app.asset_version', '1.0.0');
        }

        return $version;
    }
}

// Generate the URL for a custom asset.
if (! function_exists('custom_asset')) {
    function custom_asset(?string $file = null, ?string $default = null, ?string $prefix_path = null): ?string
    {
        if (in_array($file, [null, '', '0'], true)) {
            return $default;
        }

        $path = in_array($prefix_path, [null, '', '0'], true) ? $file : mb_rtrim($prefix_path, '/').'/'.mb_ltrim($file, '/');
        $url = resolve(UrlGenerator::class)->asset($path);

        if (should_version_asset($file)) {
            $version = get_asset_version();
            $separator = parse_url($url, PHP_URL_QUERY) ? '&' : '?';
            $url .= $separator.'v='.$version;
        }

        return $url;
    }
}

// Get the URL for a cloud asset.
if (! function_exists('cloud_asset')) {
    function cloud_asset(?string $file = null, ?string $default = null, ?string $prefix_path = null, ?string $driver = null): ?string
    {
        if (in_array($file, [null, '', '0'], true)) {
            return $default;
        }

        // Use the default driver if not provided
        $driver = in_array($driver, [null, '', '0'], true) ? config('filesystems.default') : $driver;

        // Fetch the base URL from configuration and ensure it's valid
        $url = config(sprintf('filesystems.disks.%s.asset_config.url', $driver)) ?: config(sprintf('filesystems.disks.%s.url', $driver));
        // Return the default if no valid URL is found
        if (! $url) {
            return $default;
        }

        // Construct the full URL with optional prefix and file name
        $fullPath = in_array($prefix_path, [null, '', '0'], true) ? $file : mb_rtrim($prefix_path, '/').'/'.mb_ltrim($file, '/');
        $assetUrl = mb_rtrim((string) $url, '/').'/'.$fullPath;

        // Only add version for CSS/JS files to avoid overhead on images/other assets
        if (should_version_asset($file)) {
            $version = get_asset_version();  // Removed $fullPath parameter for performance
            $separator = parse_url($assetUrl, PHP_URL_QUERY) ? '&' : '?';
            $assetUrl .= $separator.'v='.$version;
        }

        return $assetUrl;
    }
}

// Generate the URL for a storage asset.
if (! function_exists('storage_asset')) {
    function storage_asset(?string $file = null, ?string $default = null, bool $isPublic = true): ?string
    {
        if (in_array($file, [null, '', '0'], true)) {
            return $default;
        }

        // For public files, use direct storage URL (supports CDN)
        if ($isPublic) {
            return Storage::url($file);
        }

        // For private files, return a secure route that serves the file through the application
        return route('secure.storage', ['path' => base64_encode($file)]);
    }
}

// Upload a file to storage and delete the old file if it exists.
if (! function_exists('upload_file')) {
    function upload_file($request, string $fileName, string $folderName, ?string $oldImage = null, bool $isPublic = true, array $rule = []): string|bool|null
    {
        // Validate the file input if validation rules are provided
        if ($rule !== []) {
            $request->validate([
                $fileName => $rule,
            ]);
        }

        // Check if the file exists in the request
        if ($request->hasFile($fileName) && $request->file($fileName)->isValid()) {
            // Delete the old file if it exists
            if ($oldImage && Storage::exists($oldImage)) {
                Storage::delete($oldImage);
            }

            // Use the store_file helper to store the new file
            return store_file($request->file($fileName), $folderName, $isPublic);
        }

        // Return the old file path if no new file was uploaded
        return $oldImage;
    }
}

// Store a base64-encoded file in storage.
if (! function_exists('base64_store')) {
    function base64_store(string $base64, string $path, ?string $oldFile = null, ?string $disk = null, bool $isPublic = true): string|false
    {
        if ($base64 === '' || $base64 === '0' || ($path === '' || $path === '0')) {
            return false;
        }

        $disk = in_array($disk, [null, '', '0'], true) ? config('filesystems.default') : $disk;
        $visibility = $isPublic ? 'public' : 'private';

        // Clean base64 data (remove data URI prefix if present)
        if (str_contains($base64, 'data:')) {
            $base64 = explode(',', $base64, 2)[1] ?? '';
        }

        // Clean whitespace and validate
        $base64 = str_replace([' ', "\n", "\r", "\t"], '', $base64);
        if (in_array(preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $base64), [0, false], true)) {
            return false;
        }

        // Decode and store
        $data = base64_decode($base64, true);
        if ($data === false) {
            return false;
        }

        try {
            $success = Storage::disk($disk)->put($path, $data, ['visibility' => $visibility]);
            if ($success && $oldFile) {
                delete_file($oldFile, $disk);  // Pass disk parameter
            }

            return $success ? $path : false;
        } catch (Exception) {
            return false;
        }
    }
}

// Delete file from storage.
if (! function_exists('delete_file')) {
    function delete_file(?string $path, ?string $disk = null): bool
    {
        if (in_array($path, [null, '', '0'], true)) {
            return false;
        }

        $disk = in_array($disk, [null, '', '0'], true) ? config('filesystems.default') : $disk;

        try {
            // Only check existence and delete if path is valid
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->delete($path);
            }

            return true;  // File doesn't exist, consider it "deleted"
        } catch (Exception) {
            return false;
        }
    }
}

// Pad an integer to a specific length with a given character.
if (! function_exists('int_pad')) {
    function int_pad(int $number, string $padString = '0', int $padLength = 4): string
    {
        return mb_str_pad((string) $number, $padLength, $padString, STR_PAD_LEFT);
    }
}

//  Convert a size in bytes to a human-readable format.
if (! function_exists('size_convert')) {
    function size_convert(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        if ($size <= 0) {
            return '0 B';
        }

        $i = floor(log($size, 1024));

        return round($size / 1024 ** $i, 2).' '.$units[$i];
    }
}

// Read the contents of a .env file into an associative array.
if (! function_exists('readEnvFile')) {
    function readEnvFile(string $path = __DIR__.'/../../.env'): array
    {
        $env = [];
        $file = fopen($path, 'r');

        while (($line = fgets($file)) !== false) {
            $line = mb_trim($line);
            if ($line === '') {
                continue;
            }

            if ($line === '0') {
                continue;
            }

            if ($line[0] === '#') {
                continue;
            }

            [$key, $value] = explode('=', $line, 2) + [null, null];
            if ($key !== null && $value !== null) {
                $env[$key] = mb_trim($value, '"\'');
            }
        }

        fclose($file);

        return $env;
    }
}

// Write an associative array of key-value pairs to a .env file.
if (! function_exists('writeEnvFile')) {
    function writeEnvFile(array $env, string $path = __DIR__.'/../../.env'): void
    {
        $content = file_get_contents($path);

        foreach ($env as $key => $value) {
            $formattedValue = is_bool($value) ? mb_strtolower(var_export($value, true)) : (is_numeric($value) ? $value : sprintf('"%s"', $value));
            $pattern = sprintf('/^%s=.*$/m', $key);
            $replacement = sprintf('%s=%s', $key, $formattedValue);

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content);
            } else {
                $content .= PHP_EOL.$replacement;
            }
        }

        file_put_contents($path, $content);
        Artisan::call('config:cache');
    }
}

// Generate a unique transaction ID.
if (! function_exists('generateTxn')) {
    function generateTxn(string $prefix = 'TXN', int $length = 20, ?string $model = null, ?string $field = null): string
    {
        // Ensure length is sufficient for the prefix
        $prefixLength = mb_strlen($prefix);
        throw_if($length <= $prefixLength, InvalidArgumentException::class, 'Length must be greater than prefix length.');

        // Calculate remaining length for timestamp and random string after the prefix
        $remainingLength = $length - $prefixLength;

        // Use a shorter timestamp if necessary, otherwise, keep it as 'YYMMDDHHMM'
        $timestampLength = min($remainingLength, 12);  // Max 12 characters for the timestamp
        $timestamp = mb_substr(now()->format('ymdHis'), 0, $timestampLength);

        // Calculate remaining length for the random string
        $randomStringLength = $remainingLength - $timestampLength;

        // Generate a random string of the required length
        $randomString = Str::random($randomStringLength);

        // Combine prefix, timestamp, and random string to form the txn ID
        $txn = Str::upper($prefix.$timestamp.$randomString);

        // Check uniqueness if model and field are provided
        if ($model && $field) {
            $attempts = 0;
            $maxAttempts = 10;
            // Limit the number of attempts to avoid potential infinite loops
            while ($model::where($field, $txn)->exists() && $attempts < $maxAttempts) {
                $randomString = Str::random($randomStringLength);
                $txn = Str::upper($prefix.$timestamp.$randomString);
                $attempts++;
            }

            throw_if($attempts >= $maxAttempts, RuntimeException::class, 'Unable to generate a unique transaction ID after multiple attempts.');
        }

        return $txn;
    }
}

// Morph resolver alias to actual class name using morph registry.
if (! function_exists('morph_resolver')) {
    function morph_resolver(string $type): string
    {
        return resolve('morph.resolver')->resolve($type);
    }
}

// Get the current database transaction level.
if (! function_exists('db_transaction_level')) {
    function db_transaction_level(): int
    {
        return DB::transactionLevel();
    }
}

// Check if currently inside a database transaction.
if (! function_exists('in_transaction')) {
    function in_transaction(): bool
    {
        return DB::transactionLevel() > 0;
    }
}

// Get a system setting value by key.
if (! function_exists('system_setting')) {
    function system_setting(string $key, mixed $default = null, bool $file = false): mixed
    {
        $data = SystemSetting::get($key);

        if ($file && $data) {
            return Storage::url($data);
        }

        return $data ?? $default;
    }
}

// Set a system setting value.
if (! function_exists('system_setting_set')) {
    function system_setting_set(string $key, mixed $value): bool
    {
        return SystemSetting::set($key, $value);
    }
}

// Get the home route based on user permissions.
if (! function_exists('homeRoute')) {
    function homeRoute(): string
    {
        /** @var App\Models\User|null $user */
        $user = auth()->guard('web')->user();

        if (! $user) {
            return route('login');
        }

        // Check for admin panel access permission
        if ($user->can('admin_panel_access')) {
            return route('admin.dashboard');
        }

        // Default to portal dashboard
        return route('portal.dashboard');
    }
}
