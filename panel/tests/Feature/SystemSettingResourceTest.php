<?php

declare(strict_types=1);

use App\Enums\SystemSettingType;
use App\Http\Resources\System\Setting\SystemSettingResource;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

it('masks encrypted values and hides urls', function () {
    $setting = SystemSetting::factory()->create([
        'type' => SystemSettingType::TEXT,
        'value' => 'secret',
        'is_encrypted' => true,
    ]);

    $resource = (new SystemSettingResource($setting))->toArray(Request::create('/'));

    expect($resource['value'])->toBe('••••••••');
    expect($resource['url'])->toBeNull();
});

it('returns file url for file settings', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);

    Storage::disk('public')->put('files/logo.png', 'data');

    $setting = SystemSetting::factory()->create([
        'type' => SystemSettingType::IMAGE,
        'value' => 'files/logo.png',
        'is_encrypted' => false,
    ]);

    $resource = (new SystemSettingResource($setting))->toArray(Request::create('/'));

    expect($resource['value'])->toBe('files/logo.png');
    expect($resource['url'])->toContain('files/logo.png');
});
