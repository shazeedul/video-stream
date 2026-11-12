<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Tenants\Models\Tenant;
use App\Domain\Videos\Models\Video;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class VideoController extends Controller
{
    /**
     * Display a listing of videos across all tenants.
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $tenantId = $request->query('tenant_id');
        $status = $request->query('status');
        $perPage = (int) $request->query('per_page', 15);

        $query = Video::withoutTenantScope()->with(['tenant', 'user']);

        if (is_string($search) && mb_trim($search) !== '') {
            $query->search($search);
        }

        if (is_string($tenantId) && mb_trim($tenantId) !== '') {
            $query->where('tenant_id', $tenantId);
        }

        if (is_string($status) && mb_trim($status) !== '') {
            $query->ofStatus($status);
        }

        $videos = $query->latest()
            ->paginate($perPage)
            ->withQueryString();

        $tenants = Tenant::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/videos/index', [
            'videos' => $videos,
            'tenants' => $tenants,
            'filters' => [
                'search' => $search,
                'tenant_id' => $tenantId,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Display the specified video details across any tenant.
     */
    public function show(string $video): Response
    {
        $videoModel = Video::withoutTenantScope()
            ->with([
                'tenant',
                'user:id,name,email',
                'assets',
                'uploadSessions' => fn ($query) => $query->latest(),
            ])
            ->findOrFail($video);

        return Inertia::render('admin/videos/show', [
            'video' => $videoModel,
        ]);
    }

    /**
     * Remove the specified video and purge its storage assets across any tenant.
     */
    public function destroy(string $video): RedirectResponse
    {
        $videoModel = Video::withoutTenantScope()
            ->with('assets')
            ->findOrFail($video);

        foreach ($videoModel->assets as $asset) {
            if (! empty($asset->disk) && ! empty($asset->object_key)) {
                try {
                    Storage::disk($asset->disk)->delete($asset->object_key);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        if (! empty($videoModel->thumbnail_path)) {
            try {
                Storage::disk('thumbnails')->delete($videoModel->thumbnail_path);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $videoModel->delete();

        return redirect()->route('admin.videos.index')
            ->with('success', __('Video deleted successfully.'));
    }
}
