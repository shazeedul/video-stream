<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Tenants\Models\Tenant;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Enums\TenantUserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeTenantStatusRequest;
use App\Http\Requests\Admin\StoreTenantRequest;
use App\Http\Requests\Admin\UpdateTenantRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class TenantController extends Controller
{
    /**
     * Display a listing of tenants.
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $perPage = (int) $request->query('per_page', 15);

        $tenants = Tenant::query()
            ->when(is_string($search) && $search !== '', function (Builder $query) use ($search): void {
                $query->search($search);
            })
            ->when(is_string($status) && $status !== '', function (Builder $query) use ($status): void {
                $query->where('status', $status);
            })
            ->withCount('users')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('admin/tenants/index', [
            'tenants' => $tenants,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Store a newly created tenant.
     */
    public function store(StoreTenantRequest $request): RedirectResponse
    {
        /** @var array{name: string, slug: string, owner_email: string, status?: string} $validated */
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $status = $validated['status'] ?? TenantStatus::ACTIVE->value;

            $tenant = Tenant::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'status' => $status,
            ]);

            /** @var User $owner */
            $owner = User::query()->where('email', $validated['owner_email'])->firstOrFail();

            $tenant->users()->attach($owner->id, [
                'id' => (string) Str::uuid(),
                'role' => TenantRole::OWNER->value,
                'status' => TenantUserStatus::ACTIVE->value,
            ]);
        });

        return to_route('admin.tenants.index')
            ->with('success', __('Tenant created successfully.'));
    }

    /**
     * Display the specified tenant.
     */
    public function show(Tenant $tenant): Response
    {
        $tenant->load([
            'memberships.user',
        ]);
        $tenant->loadCount('users');

        return Inertia::render('admin/tenants/show', [
            'tenant' => $tenant,
        ]);
    }

    /**
     * Update the specified tenant.
     */
    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($request->validated());

        return to_route('admin.tenants.index')
            ->with('success', __('Tenant updated successfully.'));
    }

    /**
     * Update the status of the specified tenant.
     */
    public function updateStatus(ChangeTenantStatusRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update([
            'status' => $request->validated('status'),
        ]);

        return back()->with('success', __('Tenant status updated successfully.'));
    }

    /**
     * Remove the specified tenant from storage.
     */
    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        return to_route('admin.tenants.index')
            ->with('success', __('Tenant deleted successfully.'));
    }
}
