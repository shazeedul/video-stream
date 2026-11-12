<?php

declare(strict_types=1);

use App\Enums\AssetType;
use App\Enums\UploadSessionStatus;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use Illuminate\Support\Facades\Schema;

test('video domain enums contain required cases', function (): void {
    expect(VideoStatus::values())->toContain('draft', 'uploading', 'uploaded', 'queued', 'probing', 'transcoding', 'packaging', 'ready', 'failed')
        ->and(VideoVisibility::values())->toContain('private', 'unlisted', 'public')
        ->and(AssetType::values())->toContain('original', 'master', 'hls_playlist', 'hls_segment', 'thumbnail')
        ->and(UploadSessionStatus::values())->toContain('pending', 'completed', 'aborted', 'expired');
});

test('video domain tables exist with expected schema columns and indexes', function (): void {
    expect(Schema::hasTable('videos'))->toBeTrue()
        ->and(Schema::hasColumns('videos', [
            'id', 'tenant_id', 'user_id', 'title', 'description', 'visibility',
            'status', 'duration_seconds', 'thumbnail_path', 'metadata', 'created_at', 'updated_at',
        ]))->toBeTrue();

    expect(Schema::hasTable('video_assets'))->toBeTrue()
        ->and(Schema::hasColumns('video_assets', [
            'id', 'video_id', 'type', 'disk', 'object_key', 'size_bytes',
            'mime_type', 'metadata', 'created_at', 'updated_at',
        ]))->toBeTrue();

    expect(Schema::hasTable('upload_sessions'))->toBeTrue()
        ->and(Schema::hasColumns('upload_sessions', [
            'id', 'tenant_id', 'video_id', 'upload_id', 'disk', 'object_key',
            'part_size', 'total_parts', 'status', 'expires_at', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

test('video status enum provides labels, colors, and boolean helpers', function (): void {
    expect(VideoStatus::DRAFT->value)->toBe('draft')
        ->and(VideoStatus::UPLOADING->value)->toBe('uploading')
        ->and(VideoStatus::UPLOADED->value)->toBe('uploaded')
        ->and(VideoStatus::QUEUED->value)->toBe('queued')
        ->and(VideoStatus::PROBING->value)->toBe('probing')
        ->and(VideoStatus::TRANSCODING->value)->toBe('transcoding')
        ->and(VideoStatus::PACKAGING->value)->toBe('packaging')
        ->and(VideoStatus::READY->value)->toBe('ready')
        ->and(VideoStatus::FAILED->value)->toBe('failed');

    expect(VideoStatus::DRAFT->label())->toBe('Draft')
        ->and(VideoStatus::READY->label())->toBe('Ready')
        ->and(VideoStatus::FAILED->label())->toBe('Failed');

    expect(VideoStatus::DRAFT->color())->toBe('gray')
        ->and(VideoStatus::READY->color())->toBe('green')
        ->and(VideoStatus::FAILED->color())->toBe('red');

    expect(VideoStatus::READY->isReady())->toBeTrue()
        ->and(VideoStatus::DRAFT->isReady())->toBeFalse()
        ->and(VideoStatus::FAILED->isFailed())->toBeTrue()
        ->and(VideoStatus::TRANSCODING->isProcessing())->toBeTrue()
        ->and(VideoStatus::PACKAGING->isProcessing())->toBeTrue()
        ->and(VideoStatus::PROBING->isProcessing())->toBeTrue()
        ->and(VideoStatus::QUEUED->isProcessing())->toBeTrue()
        ->and(VideoStatus::READY->isProcessing())->toBeFalse()
        ->and(VideoStatus::DRAFT->canBeEdited())->toBeTrue()
        ->and(VideoStatus::READY->canBeEdited())->toBeTrue()
        ->and(VideoStatus::FAILED->canBeEdited())->toBeTrue()
        ->and(VideoStatus::UPLOADING->canBeEdited())->toBeFalse()
        ->and(VideoStatus::TRANSCODING->canBeEdited())->toBeFalse();
});

test('video visibility enum provides labels, colors, and boolean helpers', function (): void {
    expect(VideoVisibility::PRIVATE->value)->toBe('private')
        ->and(VideoVisibility::UNLISTED->value)->toBe('unlisted')
        ->and(VideoVisibility::PUBLIC->value)->toBe('public');

    expect(VideoVisibility::PRIVATE->label())->toBe('Private')
        ->and(VideoVisibility::UNLISTED->label())->toBe('Unlisted')
        ->and(VideoVisibility::PUBLIC->label())->toBe('Public');

    expect(VideoVisibility::PRIVATE->color())->toBe('gray')
        ->and(VideoVisibility::UNLISTED->color())->toBe('amber')
        ->and(VideoVisibility::PUBLIC->color())->toBe('green');

    expect(VideoVisibility::PRIVATE->isPrivate())->toBeTrue()
        ->and(VideoVisibility::UNLISTED->isUnlisted())->toBeTrue()
        ->and(VideoVisibility::PUBLIC->isPublic())->toBeTrue();
});

test('asset type enum provides labels and boolean helpers', function (): void {
    expect(AssetType::ORIGINAL->value)->toBe('original')
        ->and(AssetType::MASTER->value)->toBe('master')
        ->and(AssetType::HLS_PLAYLIST->value)->toBe('hls_playlist')
        ->and(AssetType::HLS_SEGMENT->value)->toBe('hls_segment')
        ->and(AssetType::THUMBNAIL->value)->toBe('thumbnail');

    expect(AssetType::ORIGINAL->label())->toBe('Original')
        ->and(AssetType::MASTER->label())->toBe('Master')
        ->and(AssetType::HLS_PLAYLIST->label())->toBe('HLS Playlist')
        ->and(AssetType::HLS_SEGMENT->label())->toBe('HLS Segment')
        ->and(AssetType::THUMBNAIL->label())->toBe('Thumbnail');

    expect(AssetType::ORIGINAL->isOriginal())->toBeTrue()
        ->and(AssetType::MASTER->isMaster())->toBeTrue()
        ->and(AssetType::HLS_PLAYLIST->isHls())->toBeTrue()
        ->and(AssetType::HLS_SEGMENT->isHls())->toBeTrue()
        ->and(AssetType::THUMBNAIL->isThumbnail())->toBeTrue();
});

test('upload session status enum provides labels, colors, and boolean helpers', function (): void {
    expect(UploadSessionStatus::PENDING->value)->toBe('pending')
        ->and(UploadSessionStatus::COMPLETED->value)->toBe('completed')
        ->and(UploadSessionStatus::ABORTED->value)->toBe('aborted')
        ->and(UploadSessionStatus::EXPIRED->value)->toBe('expired');

    expect(UploadSessionStatus::PENDING->label())->toBe('Pending')
        ->and(UploadSessionStatus::COMPLETED->label())->toBe('Completed')
        ->and(UploadSessionStatus::ABORTED->label())->toBe('Aborted')
        ->and(UploadSessionStatus::EXPIRED->label())->toBe('Expired');

    expect(UploadSessionStatus::PENDING->color())->toBe('amber')
        ->and(UploadSessionStatus::COMPLETED->color())->toBe('green')
        ->and(UploadSessionStatus::ABORTED->color())->toBe('red')
        ->and(UploadSessionStatus::EXPIRED->color())->toBe('gray');

    expect(UploadSessionStatus::PENDING->isPending())->toBeTrue()
        ->and(UploadSessionStatus::COMPLETED->isCompleted())->toBeTrue()
        ->and(UploadSessionStatus::ABORTED->isAborted())->toBeTrue()
        ->and(UploadSessionStatus::EXPIRED->isExpired())->toBeTrue();
});
