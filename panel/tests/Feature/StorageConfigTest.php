<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

test('minio s3 disk is configured with s3 driver and path style endpoint', function (): void {
    $config = config('filesystems.disks.minio');

    expect($config)->not->toBeNull()
        ->and($config['driver'])->toBe('s3')
        ->and($config['use_path_style_endpoint'])->toBeTrue()
        ->and($config['bucket'])->toBe('videos-original');
});

test('minio storage client can be resolved from container', function (): void {
    $disk = Storage::disk('minio');
    expect($disk)->not->toBeNull();
});

test('minio-processed and thumbnails disks are configured', function (): void {
    $processed = config('filesystems.disks.minio-processed');
    expect($processed)->not->toBeNull()
        ->and($processed['driver'])->toBe('s3')
        ->and($processed['bucket'])->toBe('videos-processed');

    $thumbnails = config('filesystems.disks.thumbnails');
    expect($thumbnails)->not->toBeNull()
        ->and($thumbnails['driver'])->toBe('s3')
        ->and($thumbnails['bucket'])->toBe('thumbnails');
});

test('can write, read, and delete file on minio disk', function (): void {
    $filename = 'tests/sample-'.uniqid().'.txt';
    $content = 'minio flysystem integration test '.time();

    $disk = Storage::disk('minio');
    expect($disk->put($filename, $content))->toBeTrue()
        ->and($disk->exists($filename))->toBeTrue()
        ->and($disk->get($filename))->toBe($content);

    expect($disk->delete($filename))->toBeTrue()
        ->and($disk->exists($filename))->toBeFalse();
});

test('storage:minio-init command initializes buckets idempotently', function (): void {
    $this->artisan('storage:minio-init')
        ->assertSuccessful();
});
