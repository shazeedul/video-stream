<?php

declare(strict_types=1);

use App\Enums\SystemSettingType;
use App\Models\SystemSetting;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\delete;

/*
 * |--------------------------------------------------------------------------
 * | System Setting Index Tests
 * |--------------------------------------------------------------------------
 */

it('displays system settings index page for admin', function () {
    $admin = adminUser();

    actingAs($admin)
        ->get(route('admin.system-settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/system-settings/Index')
            ->has('settings')
            ->has('groups')
            ->has('currentGroup')
            ->has('fieldTypes'));
});

it('denies access to system settings for unauthorized users', function () {
    $user = portalUser();

    actingAs($user)
        ->get(route('admin.system-settings.index'))
        // Portal users are redirected by admin.panel middleware
        ->assertRedirect();
});

it('filters settings by group', function () {
    $admin = adminUser();
    SystemSetting::factory()->create(['group' => 'general']);
    SystemSetting::factory()->create(['group' => 'email']);

    actingAs($admin)
        ->get(route('admin.system-settings.index', ['group' => 'email']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('currentGroup', 'email'));
});

/*
 * |--------------------------------------------------------------------------
 * | System Setting Store Tests
 * |--------------------------------------------------------------------------
 */

it('creates a new system setting', function () {
    $admin = adminUser();

    $settingData = [
        'group' => 'general',
        'name' => 'Test Setting',
        'key' => 'general.test_setting',
        'value' => 'test_value',
        'type' => SystemSettingType::TEXT->value,
        'is_visible' => true,
        'is_public' => false,
        'is_encrypted' => false,
    ];

    actingAs($admin)
        ->post(route('admin.system-settings.store'), $settingData)
        ->assertRedirect(route('admin.system-settings.index', ['group' => 'general']))
        ->assertSessionHas('success', 'Setting created successfully.');

    assertDatabaseHas('system_settings', [
        'key' => 'general.test_setting',
        'name' => 'Test Setting',
    ]);
});

it('denies setting creation without permission', function () {
    $user = portalUser();

    actingAs($user)
        ->post(route('admin.system-settings.store'), [
            'group' => 'general',
            'name' => 'Test',
            'key' => 'general.test',
            'value' => 'test',
            'type' => SystemSettingType::TEXT->value,
        ])
        // Portal users are redirected by admin.panel middleware
        ->assertRedirect();
});

/*
 * |--------------------------------------------------------------------------
 * | System Setting Update Tests
 * |--------------------------------------------------------------------------
 */

it('updates a system setting', function () {
    $admin = adminUser();
    $setting = SystemSetting::factory()->create([
        'name' => 'Old Name',
        'value' => 'old_value',
    ]);

    actingAs($admin)
        ->put(route('admin.system-settings.update', $setting), [
            'name' => 'Updated Name',
            'value' => 'new_value',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Setting updated successfully.');

    expect($setting->fresh()->name)->toBe('Updated Name');
    expect($setting->fresh()->value)->toBe('new_value');
});

it('denies setting update without permission', function () {
    $user = portalUser();
    $setting = SystemSetting::factory()->create();

    actingAs($user)
        ->put(route('admin.system-settings.update', $setting), [
            'name' => 'New Name',
        ])
        // Portal users are redirected by admin.panel middleware
        ->assertRedirect();
});

/*
 * |--------------------------------------------------------------------------
 * | System Setting Bulk Update Tests
 * |--------------------------------------------------------------------------
 */

it('bulk updates multiple settings', function () {
    $admin = adminUser();
    $setting1 = SystemSetting::factory()->create(['value' => 'old1']);
    $setting2 = SystemSetting::factory()->create(['value' => 'old2']);

    actingAs($admin)
        ->patch(route('admin.system-settings.bulk-update'), [
            'settings' => [
                ['id' => $setting1->id, 'value' => 'new1'],
                ['id' => $setting2->id, 'value' => 'new2'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Settings updated successfully.');

    expect($setting1->fresh()->value)->toBe('new1');
    expect($setting2->fresh()->value)->toBe('new2');
});

/*
 * |--------------------------------------------------------------------------
 * | System Setting Delete Tests
 * |--------------------------------------------------------------------------
 */

it('deletes a system setting', function () {
    $admin = adminUser();
    $setting = SystemSetting::factory()->create(['group' => 'general']);

    actingAs($admin)
        ->delete(route('admin.system-settings.destroy', $setting))
        ->assertRedirect(route('admin.system-settings.index', ['group' => 'general']))
        ->assertSessionHas('success', 'Setting deleted successfully.');

    expect(SystemSetting::find($setting->id))->toBeNull();
});

it('denies setting deletion without permission', function () {
    $user = portalUser();
    $setting = SystemSetting::factory()->create();

    actingAs($user)
        ->delete(route('admin.system-settings.destroy', $setting))
        // Portal users are redirected by admin.panel middleware
        ->assertRedirect();
});

/*
 * |--------------------------------------------------------------------------
 * | System Setting Move Tests
 * |--------------------------------------------------------------------------
 */

it('moves a setting up in sort order', function () {
    $admin = adminUser();
    $setting1 = SystemSetting::factory()->create(['group' => 'general', 'sort_order' => 1]);
    $setting2 = SystemSetting::factory()->create(['group' => 'general', 'sort_order' => 2]);

    actingAs($admin)
        ->post(route('admin.system-settings.move-up', $setting2))
        ->assertRedirect()
        ->assertSessionHas('success', 'Setting moved up successfully.');

    expect($setting2->fresh()->sort_order)->toBe(1);
    expect($setting1->fresh()->sort_order)->toBe(2);
});

it('moves a setting down in sort order', function () {
    $admin = adminUser();
    $setting1 = SystemSetting::factory()->create(['group' => 'general', 'sort_order' => 1]);
    $setting2 = SystemSetting::factory()->create(['group' => 'general', 'sort_order' => 2]);

    actingAs($admin)
        ->post(route('admin.system-settings.move-down', $setting1))
        ->assertRedirect()
        ->assertSessionHas('success', 'Setting moved down successfully.');

    expect($setting1->fresh()->sort_order)->toBe(2);
    expect($setting2->fresh()->sort_order)->toBe(1);
});
