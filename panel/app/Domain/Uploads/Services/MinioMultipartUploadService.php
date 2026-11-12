<?php

declare(strict_types=1);

namespace App\Domain\Uploads\Services;

use App\Domain\Uploads\Contracts\MultipartUploadServiceInterface;
use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Videos\Models\Video;
use App\Domain\Videos\Models\VideoAsset;
use App\Enums\AssetType;
use App\Enums\UploadSessionStatus;
use App\Enums\VideoStatus;
use Aws\S3\S3Client;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class MinioMultipartUploadService implements MultipartUploadServiceInterface
{
    /**
     * Minimum part size required by S3 multipart upload (5MB = 5,242,880 bytes).
     */
    public const int MIN_PART_SIZE = 5 * 1024 * 1024;

    /**
     * Maximum number of parts allowed by S3 multipart upload specification.
     */
    public const int MAX_PARTS = 10000;

    /**
     * Create a new service instance.
     */
    public function __construct(
        private readonly string $disk = 'minio',
        private readonly ?S3Client $defaultClient = null,
    ) {}

    /**
     * Initiate a multipart upload session for a video.
     */
    public function initiateUpload(
        Video $video,
        string $filename,
        int $fileSize,
        string $contentType,
        ?S3Client $client = null,
    ): UploadSession {
        $partSize = $this->calculatePartSize($fileSize);
        $totalParts = $this->calculateTotalParts($fileSize, $partSize);
        $objectKey = $this->generateObjectKey($video, $filename);

        $s3 = $this->resolveClient($client);
        $bucket = $this->getBucket();

        $result = $s3->createMultipartUpload([
            'Bucket' => $bucket,
            'Key' => $objectKey,
            'ContentType' => $contentType,
        ]);

        /** @var string $uploadId */
        $uploadId = (string) ($result['UploadId'] ?? $result->get('UploadId'));

        return DB::transaction(function () use ($video, $uploadId, $objectKey, $partSize, $totalParts): UploadSession {
            $session = UploadSession::create([
                'tenant_id' => $video->tenant_id,
                'video_id' => $video->id,
                'upload_id' => $uploadId,
                'disk' => $this->disk,
                'object_key' => $objectKey,
                'part_size' => $partSize,
                'total_parts' => $totalParts,
                'status' => UploadSessionStatus::PENDING,
                'expires_at' => now()->addHours(24),
            ]);

            $video->update(['status' => VideoStatus::UPLOADING]);

            return $session;
        });
    }

    /**
     * Get presigned upload URLs for specified part numbers.
     *
     * @param  array<int, int>  $partNumbers
     * @return array<int, string>
     */
    public function getPresignedPartUrls(
        UploadSession $session,
        array $partNumbers,
        ?S3Client $client = null,
    ): array {
        if (! $session->canAcceptParts()) {
            throw new DomainException('Upload session is not active or has expired.');
        }

        $s3 = $this->resolveClient($client);
        $bucket = $this->getBucket();
        $urls = [];

        foreach ($partNumbers as $partNumber) {
            $partNumber = (int) $partNumber;
            if ($partNumber < 1 || $partNumber > $session->total_parts) {
                throw new InvalidArgumentException("Part number {$partNumber} is out of bounds (1-{$session->total_parts}).");
            }

            $command = $s3->getCommand('UploadPart', [
                'Bucket' => $bucket,
                'Key' => $session->object_key,
                'UploadId' => $session->upload_id,
                'PartNumber' => $partNumber,
            ]);

            $request = $s3->createPresignedRequest($command, '+1 hour');
            $urls[$partNumber] = (string) $request->getUri();
        }

        return $urls;
    }

    /**
     * Complete a multipart upload session and record original video asset.
     *
     * @param  array<int, array{PartNumber: int, ETag: string}>  $parts
     */
    public function completeUpload(
        UploadSession $session,
        array $parts,
        ?S3Client $client = null,
    ): Video {
        if ($session->status !== UploadSessionStatus::PENDING) {
            throw new DomainException('Cannot complete an upload session that is not pending.');
        }

        if ($session->isExpired()) {
            throw new DomainException('Cannot complete an expired upload session.');
        }

        if (empty($parts)) {
            throw new InvalidArgumentException('Parts list cannot be empty.');
        }

        $formattedParts = [];
        $seenPartNumbers = [];

        foreach ($parts as $part) {
            if (! is_array($part)) {
                throw new InvalidArgumentException('Each part must be an array.');
            }

            $partNumber = $part['PartNumber'] ?? $part['part_number'] ?? null;
            $etag = $part['ETag'] ?? $part['etag'] ?? null;

            if ($partNumber === null || $etag === null) {
                throw new InvalidArgumentException('Each part must have a part number and ETag.');
            }

            $partNumber = (int) $partNumber;
            if ($partNumber < 1 || $partNumber > $session->total_parts) {
                throw new InvalidArgumentException("Part number {$partNumber} is out of bounds (1-{$session->total_parts}).");
            }

            if (isset($seenPartNumbers[$partNumber])) {
                throw new InvalidArgumentException("Duplicate part number {$partNumber} provided.");
            }
            $seenPartNumbers[$partNumber] = true;

            $formattedParts[] = [
                'PartNumber' => $partNumber,
                'ETag' => (string) $etag,
            ];
        }

        usort($formattedParts, fn (array $a, array $b): int => $a['PartNumber'] <=> $b['PartNumber']);

        $s3 = $this->resolveClient($client);
        $bucket = $this->getBucket();

        $s3->completeMultipartUpload([
            'Bucket' => $bucket,
            'Key' => $session->object_key,
            'UploadId' => $session->upload_id,
            'MultipartUpload' => [
                'Parts' => $formattedParts,
            ],
        ]);

        $sizeBytes = null;
        $mimeType = $this->guessMimeType($session->object_key);

        try {
            $head = $s3->headObject([
                'Bucket' => $bucket,
                'Key' => $session->object_key,
            ]);

            if (isset($head['ContentLength'])) {
                $sizeBytes = (int) $head['ContentLength'];
            }
            if (! empty($head['ContentType'])) {
                $mimeType = (string) $head['ContentType'];
            }
        } catch (Throwable) {
            // S3 mock or network may not provide headObject
        }

        return DB::transaction(function () use ($session, $sizeBytes, $mimeType): Video {
            VideoAsset::create([
                'video_id' => $session->video_id,
                'type' => AssetType::ORIGINAL,
                'disk' => $session->disk,
                'object_key' => $session->object_key,
                'size_bytes' => $sizeBytes,
                'mime_type' => $mimeType,
                'metadata' => [
                    'upload_id' => $session->upload_id,
                ],
            ]);

            $session->update(['status' => UploadSessionStatus::COMPLETED]);

            /** @var Video $video */
            $video = $session->video;
            $video->update(['status' => VideoStatus::UPLOADED]);

            return $video->fresh(['assets', 'uploadSessions']) ?? $video;
        });
    }

    /**
     * Abort a multipart upload session.
     */
    public function abortUpload(
        UploadSession $session,
        ?S3Client $client = null,
    ): void {
        if ($session->status === UploadSessionStatus::COMPLETED) {
            throw new DomainException('Cannot abort a completed upload session.');
        }

        $s3 = $this->resolveClient($client);
        $bucket = $this->getBucket();

        $s3->abortMultipartUpload([
            'Bucket' => $bucket,
            'Key' => $session->object_key,
            'UploadId' => $session->upload_id,
        ]);

        DB::transaction(function () use ($session): void {
            $session->update(['status' => UploadSessionStatus::ABORTED]);

            if ($session->video !== null) {
                $session->video->update(['status' => VideoStatus::DRAFT]);
            }
        });
    }

    /**
     * Resolve the AWS S3 client to use.
     */
    private function resolveClient(?S3Client $client = null): S3Client
    {
        if ($client !== null) {
            return $client;
        }

        if ($this->defaultClient !== null) {
            return $this->defaultClient;
        }

        /** @var \Illuminate\Filesystem\AwsS3V3Adapter $filesystem */
        $filesystem = Storage::disk($this->disk);

        return $filesystem->getClient();
    }

    /**
     * Get the configured bucket name for this disk.
     */
    private function getBucket(): string
    {
        /** @var string|null $bucket */
        $bucket = config("filesystems.disks.{$this->disk}.bucket");

        return $bucket ?: 'videos-original';
    }

    /**
     * Calculate part size ensuring at least 5MB and total parts <= 10,000.
     */
    private function calculatePartSize(int $fileSize): int
    {
        if ($fileSize <= 0) {
            throw new InvalidArgumentException('File size must be greater than zero bytes.');
        }

        return (int) max(self::MIN_PART_SIZE, (int) ceil($fileSize / self::MAX_PARTS));
    }

    /**
     * Calculate total parts given file size and part size.
     */
    private function calculateTotalParts(int $fileSize, int $partSize): int
    {
        return (int) max(1, (int) ceil($fileSize / $partSize));
    }

    /**
     * Generate standard S3 object key for original video asset.
     */
    private function generateObjectKey(Video $video, string $filename): string
    {
        $uuid = (string) Str::uuid();
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $fileWithExt = $extension !== '' ? "{$uuid}.{$extension}" : $uuid;

        return "tenants/{$video->tenant_id}/videos/{$video->id}/original/{$fileWithExt}";
    }

    /**
     * Guess MIME type from file extension.
     */
    private function guessMimeType(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return match (mb_strtolower($extension)) {
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'mkv' => 'video/x-matroska',
            'webm' => 'video/webm',
            'm4v' => 'video/x-m4v',
            'flv' => 'video/x-flv',
            'ts' => 'video/mp2t',
            default => 'video/mp4',
        };
    }
}
