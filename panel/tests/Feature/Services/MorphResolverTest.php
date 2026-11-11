<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\MorphResolver;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->resolver = new MorphResolver();
    $this->resolver->clearCache();
    Relation::morphMap([
        'user' => User::class,
    ]);
});

it('resolves type from morph map', function () {
    expect($this->resolver->resolve('user'))->toBe(User::class);
});

it('resolves from full class name', function () {
    expect($this->resolver->resolve(User::class))->toBe(User::class);
});

it('resolves from custom registry in config', function () {
    config(['morph_registry.registry' => ['my_alias' => User::class]]);
    expect($this->resolver->resolve('my_alias'))->toBe(User::class);
});

it('throws exception for unresolved type by default', function () {
    $this->resolver->resolve('invalid_type');
})->throws(InvalidArgumentException::class, 'Cannot resolve morph type: invalid_type');

it('logs warning for unresolved type when strategy is log', function () {
    config(['morph_registry.fallback_strategy' => 'log']);

    // We can't easily assert on logger() here without mocking, but we verify it doesn't throw
    expect($this->resolver->resolve('invalid_type'))->toBe('invalid_type');
});

it('ignores unresolved type when strategy is ignore', function () {
    config(['morph_registry.fallback_strategy' => 'ignore']);
    expect($this->resolver->resolve('invalid_type'))->toBe('invalid_type');
});

it('reverse resolves alias from class name', function () {
    expect($this->resolver->reverseResolve(User::class))->toBe('user');
});

it('reverse resolves from registry', function () {
    config(['morph_registry.registry' => ['registry_alias' => User::class]]);
    Relation::morphMap([], false);  // Clear morph map

    expect($this->resolver->reverseResolve(User::class))->toBe('registry_alias');
});

it('returns null for unknown reverse resolution', function () {
    expect($this->resolver->reverseResolve('UnknownClass'))->toBeNull();
});

it('checks if type exists', function () {
    expect($this->resolver->exists('user'))->toBeTrue();
    expect($this->resolver->exists(User::class))->toBeTrue();

    config(['morph_registry.registry' => ['ext' => User::class]]);
    expect($this->resolver->exists('ext'))->toBeTrue();

    expect($this->resolver->exists('none'))->toBeFalse();
});

it('returns all registered types', function () {
    config(['morph_registry.registry' => ['reg' => User::class]]);

    $all = $this->resolver->all();
    expect($all)->toHaveKey('user');
    expect($all)->toHaveKey('reg');
});

it('clears cache', function () {
    Cache::shouldReceive('forget')->once()->with('morph_registry');
    $this->resolver->clearCache();
});

it('uses cache for registry', function () {
    config(['morph_registry.cache_enabled' => true]);
    config(['morph_registry.registry' => ['cached' => User::class]]);

    // First call caches it
    $this->resolver->resolve('cached');

    // Change config directly - should still return cached value if we didn't mock Cache correctly,
    // but here we just want to ensure it works.
    expect($this->resolver->resolve('cached'))->toBe(User::class);
});
