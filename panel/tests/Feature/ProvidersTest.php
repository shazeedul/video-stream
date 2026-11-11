<?php

declare(strict_types=1);

use App\Models\User;
use App\Providers\ApiResponseServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\MorphRegistryServiceProvider;
use App\Services\MorphResolver;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

it('returns standardized success and error responses', function () {
    (new ApiResponseServiceProvider(app()))->boot();

    $success = Response::success(['ok' => true], 'Done', 201)->getData(true);
    $error = Response::error(['field' => 'required'], 'Invalid', 422)->getData(true);

    expect($success)->toMatchArray([
        'success' => true,
        'message' => 'Done',
        'data' => ['ok' => true],
        'code' => 201,
    ]);

    expect($error)->toMatchArray([
        'success' => false,
        'message' => 'Invalid',
        'errors' => ['field' => 'required'],
        'code' => 422,
    ]);
});

it('registers and boots morph registry mappings', function () {
    config([
        'morph_registry.cache_enabled' => false,
        'morph_registry.registry' => [
            'user' => User::class,
        ],
        'morph_registry.fallback_strategy' => 'exception',
    ]);

    $provider = new MorphRegistryServiceProvider(app());
    $provider->register();
    $provider->boot();

    expect(app('morph.resolver'))->toBeInstanceOf(MorphResolver::class);
    expect(Relation::morphMap())->toHaveKey('user');
});

it('logs invalid morph registry when configured', function () {
    Log::spy();

    config([
        'morph_registry.cache_enabled' => false,
        'morph_registry.registry' => [
            'bad' => 'Missing\ClassName',
        ],
        'morph_registry.fallback_strategy' => 'log',
    ]);

    $provider = new MorphRegistryServiceProvider(app());
    $provider->register();
    $provider->boot();

    Log::shouldHaveReceived('warning');
});

it('throws for invalid registry when configured', function () {
    config([
        'morph_registry.cache_enabled' => false,
        'morph_registry.registry' => [
            'bad' => 'Missing\ClassName',
        ],
        'morph_registry.fallback_strategy' => 'exception',
    ]);

    $provider = new MorphRegistryServiceProvider(app());

    expect(fn () => $provider->boot())->toThrow(InvalidArgumentException::class);
});

it('registers policies when configured', function () {
    $provider = new AppServiceProvider(app());

    $policy = new class {};

    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('policies');
    $property->setAccessible(true);
    $property->setValue($provider, [User::class => $policy::class]);

    $provider->registerPolicies();

    expect(Gate::getPolicyFor(User::class))->toBeInstanceOf($policy::class);
});
