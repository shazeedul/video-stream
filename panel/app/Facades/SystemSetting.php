<?php

declare(strict_types=1);

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection all(string $order = 'asc')
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool set(string $key, mixed $value)
 * @method static \Illuminate\Support\Collection onlyGroups(string $order = 'asc')
 * @method static \Illuminate\Support\Collection groups(string $order = 'asc')
 * @method static \Illuminate\Support\Collection group(string $group, string $order = 'asc')
 * @method static \Illuminate\Support\Collection publicSettings()
 * @method static void flush()
 *
 * @see \App\Services\SystemSettingService
 */
final class SystemSetting extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\SystemSettingService::class;
    }
}
