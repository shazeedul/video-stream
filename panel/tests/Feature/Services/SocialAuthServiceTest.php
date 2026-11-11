<?php

declare(strict_types=1);

use App\Services\SocialAuthService;
use Illuminate\Support\Facades\Config;

it('returns available providers when configured', function () {
    Config::set('services.google', [
        'client_id' => 'google-id',
        'client_secret' => 'google-secret',
    ]);
    Config::set('services.github', [
        'client_id' => 'github-id',
        'client_secret' => 'github-secret',
    ]);
    Config::set('services.facebook', [
        'client_id' => 'facebook-id',
        'client_secret' => 'facebook-secret',
    ]);
    Config::set('services.twitter', [
        'client_id' => 'twitter-id',
        'client_secret' => 'twitter-secret',
    ]);
    Config::set('services.linkedin', [
        'client_id' => 'linkedin-id',
        'client_secret' => 'linkedin-secret',
    ]);

    $providers = SocialAuthService::getAvailableProviders();

    expect($providers)->toContain('google', 'github', 'facebook', 'twitter', 'linkedin');
});

it('returns empty array when no providers are configured', function () {
    Config::set('services.google', null);
    Config::set('services.github', null);
    Config::set('services.facebook', null);
    Config::set('services.twitter', null);
    Config::set('services.linkedin', null);

    expect(SocialAuthService::getAvailableProviders())->toBeEmpty();
});
