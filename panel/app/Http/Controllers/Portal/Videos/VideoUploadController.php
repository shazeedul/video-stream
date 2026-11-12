<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Videos;

use App\Domain\Tenants\Contracts\TenantContextServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Uploads\Contracts\MultipartUploadServiceInterface;
use App\Domain\Uploads\Models\UploadSession;
use App\Domain\Videos\Models\Video;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\Videos\CompleteUploadRequest;
use App\Http\Requests\Portal\Videos\GetPartUrlsRequest;
use App\Http\Requests\Portal\Videos\InitiateUploadRequest;
use App\Models\User;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class VideoUploadController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private readonly MultipartUploadServiceInterface $uploadService,
    ) {}

    /**
     * Initiate a multipart upload session for the given video.
     */
    public function initiate(InitiateUploadRequest $request, Video $video): JsonResponse
    {
        $this->ensureTenantAccess($video, $request->user());
        $this->authorize('update', $video);

        $session = $this->uploadService->initiateUpload(
            $video,
            (string) $request->validated('filename'),
            (int) $request->validated('file_size'),
            (string) $request->validated('content_type'),
        );

        return response()->json([
            'upload_id' => $session->upload_id,
            'part_size' => $session->part_size,
            'total_parts' => $session->total_parts,
            'object_key' => $session->object_key,
            'expires_at' => $session->expires_at?->toISOString(),
        ]);
    }

    /**
     * Get presigned upload URLs for specified part numbers.
     */
    public function partUrls(GetPartUrlsRequest $request, Video $video): JsonResponse
    {
        $this->ensureTenantAccess($video, $request->user());
        $this->authorize('update', $video);

        /** @var UploadSession|null $session */
        $session = $video->uploadSessions()->latest()->first();

        if ($session === null || ! $session->canAcceptParts()) {
            return response()->json([
                'message' => __('Upload session is not active or has expired.'),
            ], 422);
        }

        try {
            /** @var array<int, int> $partNumbers */
            $partNumbers = array_map('intval', (array) $request->validated('part_numbers'));
            $urls = $this->uploadService->getPresignedPartUrls($session, $partNumbers);

            return response()->json([
                'parts' => $urls,
                'urls' => $urls,
                ...$urls,
            ]);
        } catch (InvalidArgumentException|DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Complete a multipart upload session.
     */
    public function complete(CompleteUploadRequest $request, Video $video): JsonResponse
    {
        $this->ensureTenantAccess($video, $request->user());
        $this->authorize('update', $video);

        /** @var UploadSession|null $session */
        $session = $video->uploadSessions()->latest()->first();

        if ($session === null || ! $session->canAcceptParts()) {
            return response()->json([
                'message' => __('Upload session is not active or has expired.'),
            ], 422);
        }

        try {
            $updatedVideo = $this->uploadService->completeUpload($session, $request->formattedParts());

            return response()->json([
                'message' => __('Upload completed successfully.'),
                'video' => $updatedVideo,
                ...$updatedVideo->toArray(),
            ]);
        } catch (InvalidArgumentException|DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Abort a multipart upload session.
     */
    public function abort(Request $request, Video $video): JsonResponse
    {
        $this->ensureTenantAccess($video, $request->user());
        $this->authorize('update', $video);

        /** @var UploadSession|null $session */
        $session = $video->uploadSessions()->latest()->first();

        if ($session === null || ! $session->canAcceptParts()) {
            return response()->json([
                'message' => __('Upload session is not active or has expired.'),
            ], 422);
        }

        try {
            $this->uploadService->abortUpload($session);

            return response()->json([
                'message' => __('Upload aborted successfully.'),
            ]);
        } catch (InvalidArgumentException|DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Ensure the target video belongs to the active tenant.
     */
    private function ensureTenantAccess(Video $video, ?User $user): void
    {
        $currentTenant = app()->bound('current_tenant')
            ? app('current_tenant')
            : ($user !== null ? app(TenantContextServiceInterface::class)->getCurrentTenant($user) : null);

        if ($currentTenant instanceof Tenant && (string) $video->tenant_id !== (string) $currentTenant->id) {
            abort(404);
        }
    }
}
