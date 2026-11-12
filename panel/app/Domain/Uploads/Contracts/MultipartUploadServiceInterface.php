<?php

declare(strict_types=1);

namespace App\Domain\Uploads\Contracts;

use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Videos\Models\Video;
use Aws\S3\S3Client;

interface MultipartUploadServiceInterface
{
    /**
     * Initiate a multipart upload session for a video.
     */
    public function initiateUpload(
        Video $video,
        string $filename,
        int $fileSize,
        string $contentType,
        ?S3Client $client = null,
    ): UploadSession;

    /**
     * Get presigned upload URLs for specified part numbers.
     *
     * @param  array<int, int>  $partNumbers
     * @return array<int, string> Map of partNumber => presignedUrl
     */
    public function getPresignedPartUrls(
        UploadSession $session,
        array $partNumbers,
        ?S3Client $client = null,
    ): array;

    /**
     * Complete a multipart upload session and record original video asset.
     *
     * @param  array<int, array{PartNumber: int, ETag: string}>  $parts
     */
    public function completeUpload(
        UploadSession $session,
        array $parts,
        ?S3Client $client = null,
    ): Video;

    /**
     * Abort a multipart upload session.
     */
    public function abortUpload(
        UploadSession $session,
        ?S3Client $client = null,
    ): void;
}
