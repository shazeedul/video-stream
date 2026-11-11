<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    //
});

it('shows digitalocean spaces info and test results', function () {
    Config::set('filesystems.disks.dos', [
        'key' => 'key',
        'secret' => 'secret',
        'bucket' => 'bucket',
        'region' => 'sgp1',
        'endpoint' => 'https://sgp1.digitaloceanspaces.com',
        'asset_config' => [
            'directories' => [
                'remote' => storage_path('app/public'),
            ],
        ],
    ]);

    Process::fake([
        'which rclone' => Process::result(output: 'rclone', exitCode: 0),
        'rclone version*' => Process::result(output: "rclone v1.64.0\n", exitCode: 0),
    ]);

    Artisan::call('digitalocean:spaces', ['action' => 'info']);
    expect(Artisan::output())->toContain('Configuration Status');

    Artisan::call('digitalocean:spaces', ['action' => 'test']);
    expect(Artisan::output())->toContain('Testing DigitalOcean Spaces Connection');
});

it('fails digitalocean spaces test when config missing', function () {
    Config::set('filesystems.disks.dos', [
        'key' => null,
        'secret' => null,
        'bucket' => null,
    ]);

    Artisan::call('digitalocean:spaces', ['action' => 'test']);
    expect(Artisan::output())->toContain('Missing required configuration');
});

it('performs a dry run of digitalocean storage sync', function () {
    $path = storage_path('app/public/demo');
    File::deleteDirectory($path);
    File::ensureDirectoryExists($path);
    File::put($path.'/file.txt', 'content');

    $this
        ->artisan('digitalocean:sync-storage', [
            'folder' => 'demo',
            '--dry-run' => true,
            '--verbose' => true,
        ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Dry Run')
        ->expectsOutputToContain('Found 1 files to sync');
});

it('syncs storage using PHP fallback when forced', function () {
    // Setup file
    $path = storage_path('app/public/demo');
    File::deleteDirectory($path);
    File::ensureDirectoryExists($path);
    $filePath = $path.'/file.txt';
    File::put($filePath, 'content');

    // Create a mock for the missing Facade using alias
    // We use a dedicated mock object that proxies calls
    $mockSpace = Mockery::mock('stdClass');
    $mockSpace
        ->shouldReceive('scanFileInFolder')
        ->once()
        ->with(storage_path('app/public/demo'))
        ->andReturn(['/file.txt' => $filePath]);
    $mockSpace
        ->shouldReceive('upload')
        ->once()
        ->with($filePath, 'demo/file.txt', true);
    $mockSpace
        ->shouldReceive('purgeCache')
        ->once()
        ->with('demo');

    // Mock the Facade class itself via alias
    $mockFacade = Mockery::mock('alias:App\Facades\DigitalOceanSpace');
    $mockFacade
        ->shouldReceive('create')
        ->once()
        ->andReturn($mockSpace);

    Process::fake();  // Ensure no real process runs

    $this
        ->artisan('digitalocean:sync-storage', [
            'folder' => 'demo',
            '--force-php' => true,
            '--skip-cache' => false,
        ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Using PHP fallback')
        ->expectsOutputToContain('Found 1 files to upload');
});

it('syncs storage using rclone when available', function () {
    // Check Config first? No, command builds rclone cmd.

    // Catch-all to see what command is run
    Process::fake([
        'which rclone' => Process::result(output: 'rclone', exitCode: 0),
        '*' => Process::result(output: 'Transferred: 1 file', exitCode: 0),
    ]);

    $this
        ->artisan('digitalocean:sync-storage', [
            '--threads' => 4,
        ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Using rclone for optimal performance')
        ->expectsOutputToContain('Storage synced successfully');

    Process::assertRan(fn ($run) => str_contains($run->command, 'rclone'));
});

it('falls back to PHP sync if rclone is missing', function () {
    Process::fake([
        'which rclone' => Process::result(exitCode: 1),  // Rclone not found
    ]);

    // Setup mock for fallback
    $mockSpace = Mockery::mock('stdClass');
    $mockSpace->shouldReceive('scanFileInFolder')->andReturn([]);

    $mockFacade = Mockery::mock('alias:App\Facades\DigitalOceanSpace');
    $mockFacade->shouldReceive('create')->andReturn($mockSpace);

    $this
        ->artisan('digitalocean:sync-storage', [
            'folder' => 'demo',
        ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Using PHP fallback')
        ->expectsOutputToContain('No files found to sync');
});
