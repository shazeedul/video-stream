<?php

declare(strict_types=1);

use App\Models\User;  // Assuming User has morph relation or similar, or we use generic table
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    //
});

it('lists polymorphic registry mappings', function () {
    Relation::morphMap([
        'user' => 'App\Models\User',
        'post' => 'App\Models\Post',
    ]);

    $this
        ->artisan('morph:registry')
        ->assertExitCode(0)
        ->expectsOutputToContain('Polymorphic Type Registry')
        ->expectsOutputToContain('user')
        ->expectsOutputToContain('App\Models\User');
});

it('warns if registry is empty', function () {
    // Force clear morph map using reflection
    $reflection = new ReflectionClass(Relation::class);
    $property = $reflection->getProperty('morphMap');
    $property->setAccessible(true);
    $originalMap = $property->getValue();
    $property->setValue([]);

    try {
        $this
            ->artisan('morph:registry')
            ->assertExitCode(0)
            ->expectsOutputToContain('No morph registry entries found');
    } finally {
        // Restore map to conform to other tests?
        // Or beforeEach/afterEach handles it?
        // Ideally restore.
        $property->setValue($originalMap);
    }
});

it('validates registry entries correctly', function () {
    Config::set('morph_registry.registry', [
        'valid_model' => User::class,
        'invalid_model' => 'App\NonExistentClass',
    ]);

    $this
        ->artisan('morph:registry', ['--validate' => true])
        ->assertExitCode(1)
        ->expectsOutputToContain('Validating morph registry entries')
        ->expectsOutputToContain('Found 1 invalid registry entries');
});

it('clears morph registry cache', function () {
    // Create a mock that satisfies the Repository interface
    $mockCache = Mockery::mock(Illuminate\Contracts\Cache\Repository::class);
    // If code uses store()->forget(), this handles it. But error suggests Cache::forget() direct call.
    // We add this just in case logic branches.
    $mockCache->shouldReceive('forget')->with('morph_registry')->andReturnTrue();

    // Mock Cache facade factory
    // We must expect 'driver' because framework uses it for DI (WorkCommand).
    Cache::shouldReceive('driver')->andReturn($mockCache);

    // We must ALSO expect 'forget' because the code calls Cache::forget() directly
    // and since we mocked the Facade Root, the method doesn't exist unless defined.
    Cache::shouldReceive('forget')->with('morph_registry')->once()->andReturnTrue();

    $this
        ->artisan('morph:registry', ['--clear-cache' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('Morph registry cache cleared successfully');
});

// SyncMorphRelationCommand Tests

it('does nothing if no tables found', function () {
    Schema::shouldReceive('hasTable')->andReturn(false);

    $this
        ->artisan('morph:sync')
        ->assertExitCode(0)
        ->expectsOutputToContain('No tables found to migrate');
});

it('performs dry run migration of morph types', function () {
    Schema::dropIfExists('model_has_roles');

    // Create temp table
    Schema::create('model_has_roles', function ($table) {
        $table->id();
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
    });

    // Populate with 'old' data
    DB::table('model_has_roles')->insert([
        ['model_type' => 'App\Models\User', 'model_id' => 1],
        ['model_type' => 'App\Models\User', 'model_id' => 2],
        ['model_type' => 'user', 'model_id' => 3],  // Already mapped or new type
    ]);

    // Config mapping
    Config::set('morph_registry.registry', [
        'user' => 'App\Models\User',
    ]);

    $this
        ->artisan('morph:sync', ['--dry-run' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('DRY RUN MODE')
        ->expectsOutputToContain('Found 1 morph types to migrate')
        ->expectsOutputToContain('Would have updated 2 records');

    // Verify NO changes
    $count = DB::table('model_has_roles')->where('model_type', 'App\Models\User')->count();
    expect($count)->toBe(2);
});

it('migrates morph types in database', function () {
    // Create temp table
    Schema::dropIfExists('model_has_roles');
    Schema::create('model_has_roles', function ($table) {
        $table->id();
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
    });

    DB::table('model_has_roles')->insert([
        ['model_type' => 'App\Models\User', 'model_id' => 1],
        ['model_type' => 'App\Models\Post', 'model_id' => 2],
    ]);

    Config::set('morph_registry.registry', [
        'user' => 'App\Models\User',
        'post' => 'App\Models\Post',
    ]);

    $this
        ->artisan('morph:sync')
        ->assertExitCode(0)
        ->expectsOutputToContain('Updated 2 records');

    // Verify changes
    $userType = DB::table('model_has_roles')->where('model_id', 1)->value('model_type');
    $postType = DB::table('model_has_roles')->where('model_id', 2)->value('model_type');

    expect($userType)->toBe('user');
    expect($postType)->toBe('post');
});
