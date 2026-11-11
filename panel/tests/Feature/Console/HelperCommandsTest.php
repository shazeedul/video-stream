<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

it('creates a helper file and updates generator file', function () {
    $path = storage_path('app/helpers-test');
    File::deleteDirectory($path);
    File::ensureDirectoryExists($path);

    Config::set('helpers_generator.generator_file_path', $path);
    Config::set('helpers_generator.generator_file_name', 'helpers_generator.php');

    Artisan::call('make:helper', [
        'name' => 'DemoHelper',
        '--path' => $path,
        '--force' => true,
    ]);

    expect(File::exists($path.'/DemoHelper.php'))->toBeTrue();
    expect(File::exists($path.'/helpers_generator.php'))->toBeTrue();
});

it('syncs helper generator file with detailed output', function () {
    $path = storage_path('app/helpers-sync');
    File::deleteDirectory($path);
    File::ensureDirectoryExists($path);
    File::put($path.'/Alpha.php', "<?php\n");

    Config::set('helpers_generator.scan_directories', [$path]);
    Config::set('helpers_generator.generator_file_path', $path);
    Config::set('helpers_generator.generator_file_name', 'helpers_generator.php');

    Artisan::call('helper:sync', ['--force' => true, '--detailed' => true]);

    expect(File::exists($path.'/helpers_generator.php'))->toBeTrue();
});
