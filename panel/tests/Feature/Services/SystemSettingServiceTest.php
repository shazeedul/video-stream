<?php

declare(strict_types=1);

use App\Models\SystemSetting;
use App\Services\SystemSettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->service = new SystemSettingService();
    SystemSetting::truncate();
});

it('retrieves all settings', function () {
    SystemSetting::factory()->count(3)->create(['name' => 'Test Setting']);

    expect($this->service->all())->toHaveCount(3);
});

it('retrieves all settings in descending order', function () {
    SystemSetting::factory()->create(['name' => 'First', 'key' => 'a.first', 'sort_order' => 1]);
    SystemSetting::factory()->create(['name' => 'Second', 'key' => 'b.second', 'sort_order' => 2]);

    $settings = $this->service->all('desc');
    expect($settings->first()->sort_order)->toBeGreaterThan($settings->last()->sort_order);
});

it('retrieves specific setting by key', function () {
    SystemSetting::factory()->create(['key' => 'site_name', 'value' => 'My App', 'name' => 'Site Name']);
    expect($this->service->get('site_name'))->toBe('My App');
    expect($this->service->get('non_existent', 'default'))->toBe('default');
});

it('sets a setting value', function () {
    // Pre-create the setting to avoid NOT NULL constraint on 'name' for new records
    SystemSetting::factory()->create(['key' => 'existing_key', 'name' => 'Existing Name']);

    $this->service->set('existing_key', 'updated_value');

    expect(SystemSetting::where('key', 'existing_key')->first()->value)->toBe('updated_value');
});

it('retrieves distinct groups', function () {
    SystemSetting::factory()->create(['group' => 'general', 'name' => 'General Setting 1']);
    SystemSetting::factory()->create(['group' => 'general', 'name' => 'General Setting 2']);
    SystemSetting::factory()->create(['group' => 'security', 'name' => 'Security Setting']);

    expect($this->service->onlyGroups())->toHaveCount(2);
    expect($this->service->onlyGroups())->toContain('general', 'security');
});

it('retrieves distinct groups in descending order', function () {
    SystemSetting::factory()->create(['group' => 'alpha', 'name' => 'Alpha Setting']);
    SystemSetting::factory()->create(['group' => 'zeta', 'name' => 'Zeta Setting']);

    $groups = $this->service->onlyGroups('desc');
    expect($groups->first())->toBe('zeta');
});

it('retrieves grouped settings', function () {
    SystemSetting::factory()->create(['group' => 'A', 'key' => 'k1', 'name' => 'Setting A1']);
    SystemSetting::factory()->create(['group' => 'A', 'key' => 'k2', 'name' => 'Setting A2']);
    SystemSetting::factory()->create(['group' => 'B', 'key' => 'k3', 'name' => 'Setting B1']);

    $groups = $this->service->groups();
    expect($groups)->toHaveCount(2);
    expect($groups['A'])->toHaveCount(2);
});

it('retrieves grouped settings in descending order', function () {
    SystemSetting::factory()->create(['group' => 'A', 'key' => 'a.k1', 'name' => 'Setting A1', 'sort_order' => 1]);
    SystemSetting::factory()->create(['group' => 'A', 'key' => 'a.k2', 'name' => 'Setting A2', 'sort_order' => 2]);

    $groups = $this->service->groups('desc');
    expect($groups['A'][0]->sort_order)->toBeGreaterThan($groups['A'][1]->sort_order);
});

it('retrieves settings for a specific group', function () {
    SystemSetting::factory()->create(['group' => 'G1', 'key' => 'k1', 'name' => 'Group 1 Setting']);
    SystemSetting::factory()->create(['group' => 'G2', 'key' => 'k2', 'name' => 'Group 2 Setting']);

    expect($this->service->group('G1'))->toHaveCount(1);
    expect($this->service->group('G1')->first()->key)->toBe('k1');
});

it('retrieves public settings for frontend', function () {
    SystemSetting::factory()->create([
        'key' => 'logo',
        'name' => 'Logo',
        'value' => 'logo.png',
        'is_public' => true,
        'is_encrypted' => false,
        'is_visible' => true,
    ]);

    SystemSetting::factory()->create([
        'key' => 'secret',
        'name' => 'Secret',
        'value' => 'topsecret',
        'is_public' => false,
    ]);

    $public = $this->service->publicSettings();
    expect($public)->toHaveKey('logo');
    expect($public)->not->toHaveKey('secret');
});

it('flushes cache', function () {
    Cache::shouldReceive('forget')->atLeast()->once();
    // Also likely triggers Cache::tags if supported, but we'll mock the simple behavior
    $this->service->flush();
});

it('gets file url for image settings', function () {
    $setting = SystemSetting::factory()->create([
        'key' => 'app_logo',
        'name' => 'App Logo',
        'value' => 'logos/main.png',
        'type' => App\Enums\SystemSettingType::IMAGE,
        'is_public' => true,
    ]);

    Storage::fake('public');

    $public = $this->service->publicSettings();
    expect($public['app_logo']['url'])->toContain('logos/main.png');
});

it('returns null url for non-file type settings', function () {
    SystemSetting::factory()->create([
        'key' => 'site_title',
        'name' => 'Site Title',
        'value' => 'My Website',
        'type' => App\Enums\SystemSettingType::TEXT,
        'is_public' => true,
        'is_visible' => true,
        'is_encrypted' => false,
    ]);

    $public = $this->service->publicSettings();
    expect($public['site_title']['url'])->toBeNull();
});

it('returns null url for empty file settings', function () {
    SystemSetting::factory()->create([
        'key' => 'favicon',
        'name' => 'Favicon',
        'value' => null,
        'type' => App\Enums\SystemSettingType::IMAGE,
        'is_public' => true,
        'is_visible' => true,
        'is_encrypted' => false,
    ]);

    $public = $this->service->publicSettings();
    expect($public['favicon']['url'])->toBeNull();
});

it('skips cache when caching disabled', function () {
    Config::set('system-setting.cache.enabled', false);
    $service = new SystemSettingService();

    SystemSetting::factory()->count(2)->create(['group' => 'general']);

    expect($service->all())->toHaveCount(2);
    expect($service->onlyGroups())->toContain('general');
});

it('falls back when cache tags are unsupported', function () {
    SystemSetting::factory()->create(['group' => 'group-a', 'name' => 'Setting A']);

    Cache::shouldReceive('tags')->andThrow(new Exception('unsupported'));
    Cache::shouldReceive('forget')->atLeast()->once();

    $this->service->flush();
});
