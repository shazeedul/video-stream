<?php

declare(strict_types=1);

use App\Models\SystemSetting;

/*
 * |--------------------------------------------------------------------------
 * | SystemSetting Group Tests
 * |--------------------------------------------------------------------------
 */

it('returns all distinct groups', function () {
    SystemSetting::factory()->create(['group' => 'general']);
    SystemSetting::factory()->create(['group' => 'email']);
    SystemSetting::factory()->create(['group' => 'general']);

    $groups = SystemSetting::groups();

    expect($groups)->toContain('general');
    expect($groups)->toContain('email');
});

/*
 * |--------------------------------------------------------------------------
 * | SystemSetting Scope Tests
 * |--------------------------------------------------------------------------
 */

it('filters visible settings', function () {
    SystemSetting::factory()->create(['is_visible' => true]);
    SystemSetting::factory()->create(['is_visible' => false]);

    $visibleSettings = SystemSetting::visible()->get();

    expect($visibleSettings)->toHaveCount(1);
});

it('filters settings by group', function () {
    SystemSetting::factory()->create(['group' => 'general']);
    SystemSetting::factory()->create(['group' => 'email']);

    $generalSettings = SystemSetting::whereGroup('general')->get();

    expect($generalSettings)->toHaveCount(1);
});

it('sorts settings by sort_order', function () {
    SystemSetting::factory()->create(['group' => 'general', 'sort_order' => 3]);
    SystemSetting::factory()->create(['group' => 'general', 'sort_order' => 1]);
    SystemSetting::factory()->create(['group' => 'general', 'sort_order' => 2]);

    $settings = SystemSetting::whereGroup('general')->sortOrder()->get();

    expect($settings->first()->sort_order)->toBe(1);
    expect($settings->last()->sort_order)->toBe(3);
});

/*
 * |--------------------------------------------------------------------------
 * | SystemSetting Move Tests
 * |--------------------------------------------------------------------------
 */

it('moves setting up in sort order', function () {
    $setting1 = SystemSetting::factory()->create(['group' => 'general', 'sort_order' => 1]);
    $setting2 = SystemSetting::factory()->create(['group' => 'general', 'sort_order' => 2]);

    $setting2->moveUp();

    expect($setting2->fresh()->sort_order)->toBe(1);
    expect($setting1->fresh()->sort_order)->toBe(2);
});

it('moves setting down in sort order', function () {
    $setting1 = SystemSetting::factory()->create(['group' => 'general', 'sort_order' => 1]);
    $setting2 = SystemSetting::factory()->create(['group' => 'general', 'sort_order' => 2]);

    $setting1->moveDown();

    expect($setting1->fresh()->sort_order)->toBe(2);
    expect($setting2->fresh()->sort_order)->toBe(1);
});

/*
 * |--------------------------------------------------------------------------
 * | SystemSetting Encryption Tests
 * |--------------------------------------------------------------------------
 */

it('checks if setting is encrypted', function () {
    $encryptedSetting = SystemSetting::factory()->create(['is_encrypted' => true]);
    $normalSetting = SystemSetting::factory()->create(['is_encrypted' => false]);

    expect($encryptedSetting->isEncrypted())->toBeTrue();
    expect($normalSetting->isEncrypted())->toBeFalse();
});

it('checks if setting is public', function () {
    $publicSetting = SystemSetting::factory()->create(['is_public' => true]);
    $privateSetting = SystemSetting::factory()->create(['is_public' => false]);

    expect($publicSetting->isPublic())->toBeTrue();
    expect($privateSetting->isPublic())->toBeFalse();
});

it('checks if encrypted setting can be public', function () {
    $encryptedSetting = SystemSetting::factory()->create(['is_encrypted' => true]);
    $normalSetting = SystemSetting::factory()->create(['is_encrypted' => false]);

    expect($encryptedSetting->canBePublic())->toBeFalse();
    expect($normalSetting->canBePublic())->toBeTrue();
});

/*
 * |--------------------------------------------------------------------------
 * | SystemSetting Auto Sort Order Tests
 * |--------------------------------------------------------------------------
 */

it('auto-sets sort order on create', function () {
    SystemSetting::factory()->create(['group' => 'general', 'sort_order' => 1]);
    $setting = SystemSetting::factory()->create(['group' => 'general', 'sort_order' => null]);

    expect($setting->sort_order)->toBe(2);
});

it('decrypts encrypted values and exposes raw key', function () {
    $setting = new SystemSetting();
    $setting->key = 'general.api_key';
    $setting->name = 'Test Setting';  // Required field
    $setting->is_encrypted = true;
    $setting->value = 'secret-value';
    $setting->save();

    $setting->refresh();

    expect($setting->value)->toBe('secret-value');
    expect($setting->raw_key)->toBe('api_key');
    expect($setting->getRawOriginal('value'))->not->toBe('secret-value');
});

it('returns null when decrypting invalid encrypted value', function () {
    $setting = SystemSetting::factory()->create([
        'key' => 'general.bad',
        'value' => 'not-encrypted',
        'is_encrypted' => true,
    ]);

    $setting->setRawAttributes([
        'value' => 'not-encrypted',
        'is_encrypted' => true,
    ], true);

    expect($setting->value)->toBeNull();
});

it('returns false when moving setting with no sibling', function () {
    $setting = SystemSetting::factory()->create(['group' => 'general', 'sort_order' => 1]);

    expect($setting->moveUp())->toBeFalse();
    expect($setting->moveDown())->toBeFalse();
});
