<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Playback\Contracts\PlaybackTokenServiceInterface;
use App\Domain\Playback\Services\PlaybackTokenService;
use App\Domain\Processing\Contracts\RabbitMQPublisherInterface;
use App\Domain\Processing\Services\RabbitMQPublisherService;
use App\Domain\Tenants\Contracts\TenantContextServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\TenantContextService;
use App\Domain\Uploads\Contracts\MultipartUploadServiceInterface;
use App\Domain\Uploads\Services\MinioMultipartUploadService;
use App\Domain\Videos\Models\Video;
use App\Policies\TenantPolicy;
use App\Policies\VideoPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     */
    private array $policies = [
        Tenant::class => TenantPolicy::class,
        Video::class => VideoPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TenantContextServiceInterface::class, TenantContextService::class);
        $this->app->bind(MultipartUploadServiceInterface::class, MinioMultipartUploadService::class);
        $this->app->bind(RabbitMQPublisherInterface::class, RabbitMQPublisherService::class);
        $this->app->singleton(PlaybackTokenServiceInterface::class, PlaybackTokenService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

    }

    /**
     * Register the application's policies.
     */
    public function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
