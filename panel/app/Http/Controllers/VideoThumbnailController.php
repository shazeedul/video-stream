<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Videos\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class VideoThumbnailController extends Controller
{
    /**
     * Serve video thumbnail by video model/ID.
     */
    public function show(Request $request, string $videoId): Response
    {
        /** @var Video|null $video */
        $video = Video::withoutTenantScope()->find($videoId);

        if ($video === null || empty($video->thumbnail_path)) {
            return response('Thumbnail not found', 404);
        }

        return $this->respondWithFile($video->thumbnail_path);
    }

    /**
     * Serve thumbnail by storage path (e.g. /storage/tenants/... or /tenants/...).
     */
    public function showStorage(Request $request, string $path): Response
    {
        $normalizedPath = 'tenants/'.mb_ltrim($path, '/');

        return $this->respondWithFile($normalizedPath);
    }

    private function respondWithFile(string $path): Response
    {
        $disk = Storage::disk('thumbnails');

        if (! $disk->exists($path)) {
            return response('Thumbnail not found', 404);
        }

        $stream = $disk->readStream($path);
        if ($stream === null) {
            return response('Unable to read thumbnail', 500);
        }

        $size = $disk->size($path);

        $headers = [
            'Content-Type' => 'image/jpeg',
            'Content-Length' => (string) $size,
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=86400',
        ];

        return new StreamedResponse(function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }
}
