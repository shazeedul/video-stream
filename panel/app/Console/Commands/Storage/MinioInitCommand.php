<?php

declare(strict_types=1);

namespace App\Console\Commands\Storage;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Description('Initialize MinIO buckets for video platform (videos-original, videos-processed, thumbnails)')]
#[Signature('storage:minio-init')]
final class MinioInitCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Initializing MinIO buckets...');

        $buckets = array_values(array_unique(array_filter([
            (string) config('filesystems.disks.minio.bucket', 'videos-original'),
            (string) config('filesystems.disks.minio-processed.bucket', 'videos-processed'),
            (string) config('filesystems.disks.thumbnails.bucket', 'thumbnails'),
        ])));

        $client = $this->resolveS3Client();

        foreach ($buckets as $bucket) {
            try {
                if ($client->doesBucketExist($bucket)) {
                    $this->line("Bucket [{$bucket}] already exists.");
                } else {
                    $client->createBucket(['Bucket' => $bucket]);
                    $this->info("Bucket [{$bucket}] created successfully.");
                }

                $client->putBucketCors([
                    'Bucket' => $bucket,
                    'CORSConfiguration' => [
                        'CORSRules' => [
                            [
                                'AllowedHeaders' => ['*'],
                                'AllowedMethods' => ['GET', 'PUT', 'POST', 'DELETE', 'HEAD'],
                                'AllowedOrigins' => ['*'],
                                'ExposeHeaders' => ['ETag'],
                                'MaxAgeSeconds' => 3000,
                            ],
                        ],
                    ],
                ]);
                $this->line("CORS configured for bucket [{$bucket}].");
            } catch (S3Exception $e) {
                if (in_array($e->getAwsErrorCode(), ['BucketAlreadyOwnedByYou', 'BucketAlreadyExists'], true)) {
                    $this->line("Bucket [{$bucket}] already exists.");

                    try {
                        $client->putBucketCors([
                            'Bucket' => $bucket,
                            'CORSConfiguration' => [
                                'CORSRules' => [
                                    [
                                        'AllowedHeaders' => ['*'],
                                        'AllowedMethods' => ['GET', 'PUT', 'POST', 'DELETE', 'HEAD'],
                                        'AllowedOrigins' => ['*'],
                                        'ExposeHeaders' => ['ETag'],
                                        'MaxAgeSeconds' => 3000,
                                    ],
                                ],
                            ],
                        ]);
                        $this->line("CORS configured for bucket [{$bucket}].");
                    } catch (Throwable $corsError) {
                        $this->warn("Could not set CORS for bucket [{$bucket}]: {$corsError->getMessage()}");
                    }

                    continue;
                }

                $this->error("Failed to create bucket [{$bucket}]: {$e->getMessage()}");

                return Command::FAILURE;
            } catch (Throwable $e) {
                $this->error("Error initializing bucket [{$bucket}]: {$e->getMessage()}");

                return Command::FAILURE;
            }
        }

        $this->info('MinIO buckets initialization complete.');

        return Command::SUCCESS;
    }

    /**
     * Resolve the AWS S3 client instance.
     */
    private function resolveS3Client(): S3Client
    {
        $disk = Storage::disk('minio');

        if (method_exists($disk, 'getClient')) {
            /** @var S3Client $client */
            $client = $disk->getClient();

            return $client;
        }

        return new S3Client([
            'version' => 'latest',
            'region' => (string) config('filesystems.disks.minio.region', 'us-east-1'),
            'endpoint' => (string) config('filesystems.disks.minio.endpoint', 'http://127.0.0.1:9000'),
            'use_path_style_endpoint' => (bool) config('filesystems.disks.minio.use_path_style_endpoint', true),
            'credentials' => [
                'key' => (string) config('filesystems.disks.minio.key', 'minioadmin'),
                'secret' => (string) config('filesystems.disks.minio.secret', 'minioadmin'),
            ],
        ]);
    }
}
