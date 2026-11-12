<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Videos;

use App\Domain\Tenants\Contracts\TenantContextServiceInterface;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\Videos\StoreVideoRequest;
use App\Http\Requests\Portal\Videos\UpdateVideoRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class VideoController extends Controller
{
    /**
     * Display a listing of videos for the active tenant.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Video::class);

        $search = $request->query('search');
        $status = $request->query('status');
        $visibility = $request->query('visibility');

        $query = Video::query()->with('user:id,name,email');

        if ($search !== null && mb_trim((string) $search) !== '') {
            $query->search((string) $search);
        }

        if ($status !== null && mb_trim((string) $status) !== '') {
            $query->ofStatus((string) $status);
        }

        if ($visibility !== null && mb_trim((string) $visibility) !== '') {
            $query->ofVisibility((string) $visibility);
        }

        $videos = $query->latest()->paginate(12)->withQueryString();

        $stats = [
            'total' => Video::count(),
            'ready' => Video::ofStatus(VideoStatus::READY)->count(),
            'processing' => Video::whereIn('status', [
                VideoStatus::UPLOADING,
                VideoStatus::UPLOADED,
                VideoStatus::QUEUED,
                VideoStatus::TRANSCODING,
            ])->count(),
            'draft' => Video::ofStatus(VideoStatus::DRAFT)->count(),
            'failed' => Video::ofStatus(VideoStatus::FAILED)->count(),
        ];

        return Inertia::render('portal/videos/index', [
            'videos' => $videos,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'visibility' => $visibility,
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Create a new draft video for the active tenant.
     */
    public function store(StoreVideoRequest $request): RedirectResponse
    {
        $this->authorize('create', Video::class);

        $currentTenant = app()->bound('current_tenant')
            ? app('current_tenant')
            : ($request->user() !== null ? app(TenantContextServiceInterface::class)->getCurrentTenant($request->user()) : null);

        if (! $currentTenant instanceof Tenant) {
            abort(404, __('Organization not found.'));
        }

        $video = Video::create([
            'tenant_id' => $currentTenant->id,
            'user_id' => $request->user()?->id,
            'title' => (string) $request->validated('title'),
            'description' => $request->validated('description'),
            'visibility' => $request->validated('visibility') ?? VideoVisibility::PRIVATE->value,
            'status' => VideoStatus::DRAFT,
        ]);

        return redirect()->route('portal.videos.show', $video)->with('success', __('Video created successfully.'));
    }

    /**
     * Display the specified video details.
     */
    public function show(Request $request, Video $video): Response
    {
        $this->ensureTenantAccess($video, $request->user());
        $this->authorize('view', $video);

        $video->load([
            'user:id,name,email',
            'assets',
            'uploadSessions' => fn ($query) => $query->latest(),
        ]);

        $video->setRelation('latest_upload_session', $video->uploadSessions->first());

        return Inertia::render('portal/videos/show', [
            'video' => $video,
        ]);
    }

    /**
     * Update the specified video metadata.
     */
    public function update(UpdateVideoRequest $request, Video $video): RedirectResponse
    {
        $this->ensureTenantAccess($video, $request->user());
        $this->authorize('update', $video);

        $video->update($request->validated());

        return back()->with('success', __('Video updated successfully.'));
    }

    /**
     * Remove the specified video and its storage assets.
     */
    public function destroy(Request $request, Video $video): RedirectResponse
    {
        $this->ensureTenantAccess($video, $request->user());
        $this->authorize('delete', $video);

        $video->loadMissing('assets');

        foreach ($video->assets as $asset) {
            if (! empty($asset->disk) && ! empty($asset->object_key)) {
                try {
                    Storage::disk($asset->disk)->delete($asset->object_key);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        if (! empty($video->thumbnail_path)) {
            try {
                Storage::disk('thumbnails')->delete($video->thumbnail_path);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $video->delete();

        return redirect()->route('portal.videos.index')->with('success', __('Video deleted successfully.'));
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
