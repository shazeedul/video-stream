<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SystemSettingType;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

final class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // ============================================
            // APPLICATION SETTINGS
            // ============================================
            [
                'group' => 'app',
                'name' => 'App Name',
                'key' => 'app.name',
                'value' => 'DevWizard',
                'type' => SystemSettingType::TEXT->value,
                'rules' => ['maxlength' => 100],
                'remarks' => 'Application name displayed throughout the site',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 1,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Logo Dark (PNG)',
                'key' => 'app.logo_dark',
                'value' => 'system-settings/logo-dark.png',
                'type' => SystemSettingType::IMAGE->value,
                'rules' => ['accept' => 'image/png', 'maxsize' => 2048],
                'remarks' => 'Logo PNG for dark mode (max 2MB)',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 2,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Logo Dark (SVG)',
                'key' => 'app.logo_dark_svg',
                'value' => 'system-settings/logo-dark.svg',
                'type' => SystemSettingType::IMAGE->value,
                'rules' => ['accept' => 'image/svg+xml', 'maxsize' => 2048],
                'remarks' => 'Logo SVG for dark mode (max 2MB)',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 3,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Logo Light (PNG)',
                'key' => 'app.logo_light',
                'value' => 'system-settings/logo-light.png',
                'type' => SystemSettingType::IMAGE->value,
                'rules' => ['accept' => 'image/png', 'maxsize' => 2048],
                'remarks' => 'Logo PNG for light mode (max 2MB)',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 4,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Logo Light (SVG)',
                'key' => 'app.logo_light_svg',
                'value' => 'system-settings/logo-light.svg',
                'type' => SystemSettingType::IMAGE->value,
                'rules' => ['accept' => 'image/svg+xml', 'maxsize' => 2048],
                'remarks' => 'Logo SVG for light mode (max 2MB)',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 5,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Icon (PNG)',
                'key' => 'app.icon',
                'value' => 'system-settings/icon.png',
                'type' => SystemSettingType::IMAGE->value,
                'rules' => ['accept' => 'image/png', 'maxsize' => 512],
                'remarks' => 'App icon PNG (max 512KB)',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 6,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Icon (SVG)',
                'key' => 'app.icon_svg',
                'value' => 'system-settings/icon.svg',
                'type' => SystemSettingType::IMAGE->value,
                'rules' => ['accept' => 'image/svg+xml', 'maxsize' => 512],
                'remarks' => 'App icon SVG (max 512KB)',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 7,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Icon Dark (PNG)',
                'key' => 'app.icon_dark',
                'value' => 'system-settings/icon-dark.png',
                'type' => SystemSettingType::IMAGE->value,
                'rules' => ['accept' => 'image/png', 'maxsize' => 512],
                'remarks' => 'App icon PNG for dark mode (max 512KB)',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 8,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Icon Dark (SVG)',
                'key' => 'app.icon_dark_svg',
                'value' => 'system-settings/icon-dark.svg',
                'type' => SystemSettingType::IMAGE->value,
                'rules' => ['accept' => 'image/svg+xml', 'maxsize' => 512],
                'remarks' => 'App icon SVG for dark mode (max 512KB)',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 10,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Icon Light (PNG)',
                'key' => 'app.icon_light',
                'value' => 'system-settings/icon-light.png',
                'type' => SystemSettingType::IMAGE->value,
                'rules' => ['accept' => 'image/png', 'maxsize' => 512],
                'remarks' => 'App icon PNG for light mode (max 512KB)',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 9,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Icon Light (SVG)',
                'key' => 'app.icon_light_svg',
                'value' => 'system-settings/icon-light.svg',
                'type' => SystemSettingType::IMAGE->value,
                'rules' => ['accept' => 'image/svg+xml', 'maxsize' => 512],
                'remarks' => 'App icon SVG for light mode (max 512KB)',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 10,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Favicon (ICO)',
                'key' => 'app.favicon',
                'value' => 'system-settings/favicon.ico',
                'type' => SystemSettingType::IMAGE->value,
                'rules' => ['accept' => 'image/x-icon', 'maxsize' => 512],
                'remarks' => 'Favicon in ICO format (max 512KB)',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 11,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Favicon Dark (ICO)',
                'key' => 'app.favicon_dark',
                'value' => 'system-settings/favicon-dark.ico',
                'type' => SystemSettingType::IMAGE->value,
                'rules' => ['accept' => 'image/x-icon', 'maxsize' => 512],
                'remarks' => 'Favicon ICO for dark mode (max 512KB)',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 12,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Favicon Dark (SVG)',
                'key' => 'app.favicon_dark_svg',
                'value' => 'system-settings/favicon-dark.svg',
                'type' => SystemSettingType::IMAGE->value,
                'rules' => ['accept' => 'image/svg+xml', 'maxsize' => 512],
                'remarks' => 'Favicon SVG for dark mode (max 512KB)',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 13,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Favicon Light (ICO)',
                'key' => 'app.favicon_light',
                'value' => 'system-settings/favicon-light.ico',
                'type' => SystemSettingType::IMAGE->value,
                'rules' => ['accept' => 'image/x-icon', 'maxsize' => 512],
                'remarks' => 'Favicon ICO for light mode (max 512KB)',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 14,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Favicon Light (SVG)',
                'key' => 'app.favicon_light_svg',
                'value' => 'system-settings/favicon-light.svg',
                'type' => SystemSettingType::IMAGE->value,
                'rules' => ['accept' => 'image/svg+xml', 'maxsize' => 512],
                'remarks' => 'Favicon SVG for light mode (max 512KB)',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 15,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Apple Touch Icon Dark',
                'key' => 'app.apple_touch_icon_dark',
                'value' => 'system-settings/apple-touch-icon-dark.png',
                'type' => SystemSettingType::IMAGE->value,
                'rules' => ['accept' => 'image/png', 'maxsize' => 512],
                'remarks' => 'Apple touch icon 180x180 PNG for dark mode (max 512KB)',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 16,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Apple Touch Icon Light',
                'key' => 'app.apple_touch_icon_light',
                'value' => 'system-settings/apple-touch-icon-light.png',
                'type' => SystemSettingType::IMAGE->value,
                'rules' => ['accept' => 'image/png', 'maxsize' => 512],
                'remarks' => 'Apple touch icon 180x180 PNG for light mode (max 512KB)',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 17,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Support Email',
                'key' => 'app.support_email',
                'value' => 'support@example.com',
                'type' => SystemSettingType::EMAIL->value,
                'rules' => ['placeholder' => 'support@example.com'],
                'remarks' => 'Support contact email address',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 5,
                'is_visible' => true,
            ],
            [
                'group' => 'app',
                'name' => 'App Administrator Email',
                'key' => 'app.admin_email',
                'value' => 'admin@example.com',
                'type' => SystemSettingType::EMAIL->value,
                'rules' => ['placeholder' => 'admin@example.com'],
                'remarks' => 'Administrator email for system notifications',
                'is_encrypted' => false,
                'is_public' => false,
                'sort_order' => 6,
                'is_visible' => true,
            ],
            // ============================================
            // SOCIAL MEDIA SETTINGS
            // ============================================
            [
                'group' => 'social',
                'name' => 'Facebook URL',
                'key' => 'social.facebook_url',
                'value' => '',
                'type' => SystemSettingType::URL->value,
                'rules' => ['placeholder' => 'https://facebook.com/yourpage'],
                'remarks' => 'Facebook page URL',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 1,
                'is_visible' => true,
            ],
            [
                'group' => 'social',
                'name' => 'Twitter/X URL',
                'key' => 'social.twitter_url',
                'value' => '',
                'type' => SystemSettingType::URL->value,
                'rules' => ['placeholder' => 'https://twitter.com/yourhandle'],
                'remarks' => 'Twitter/X profile URL',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 2,
                'is_visible' => true,
            ],
            [
                'group' => 'social',
                'name' => 'Instagram URL',
                'key' => 'social.instagram_url',
                'value' => '',
                'type' => SystemSettingType::URL->value,
                'rules' => ['placeholder' => 'https://instagram.com/yourhandle'],
                'remarks' => 'Instagram profile URL',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 3,
                'is_visible' => true,
            ],
            [
                'group' => 'social',
                'name' => 'YouTube URL',
                'key' => 'social.youtube_url',
                'value' => '',
                'type' => SystemSettingType::URL->value,
                'rules' => ['placeholder' => 'https://youtube.com/@yourchannel'],
                'remarks' => 'YouTube channel URL',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 4,
                'is_visible' => true,
            ],
            [
                'group' => 'social',
                'name' => 'LinkedIn URL',
                'key' => 'social.linkedin_url',
                'value' => '',
                'type' => SystemSettingType::URL->value,
                'rules' => ['placeholder' => 'https://linkedin.com/company/yourcompany'],
                'remarks' => 'LinkedIn company/profile URL',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 5,
                'is_visible' => true,
            ],
            [
                'group' => 'social',
                'name' => 'GitHub URL',
                'key' => 'social.github_url',
                'value' => '',
                'type' => SystemSettingType::URL->value,
                'rules' => ['placeholder' => 'https://github.com/yourorg'],
                'remarks' => 'GitHub organization/profile URL',
                'is_encrypted' => false,
                'is_public' => true,
                'sort_order' => 6,
                'is_visible' => true,
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        // copy logo and favicon to system-settings
        if (! File::exists(storage_path('app/public/system-settings'))) {
            File::makeDirectory(storage_path('app/public/system-settings'));
        }

        // go to public_path('assets/assets/images/*' and copy to storage_path('app/public/system-settings

        $sourcePath = public_path('assets/images');
        $destinationPath = storage_path('app/public/system-settings');

        if (File::exists($destinationPath)) {
            File::deleteDirectory($destinationPath);
        }

        File::makeDirectory($destinationPath);

        File::copyDirectory($sourcePath, $destinationPath);
    }
}
