<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Role\DeleteRoleAction;
use App\Actions\Role\StoreRoleAction;
use App\Actions\Role\UpdateRoleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $isDefault = $request->get('is_default');
        $sort = $request->get('sort', 'name');

        $validSorts = ['name', 'created_at', 'permissions_count'];
        $sort = in_array($sort, $validSorts) ? $sort : 'name';

        $roles = Role::query()
            ->select(['id', 'name', 'display_name', 'description', 'guard_name', 'is_default', 'created_at'])
            ->with(['permissions:id,name'])
            ->when($search, function ($query, $search): void {
                $query
                    ->where('name', 'like', sprintf('%%%s%%', $search))
                    ->orWhere('display_name', 'like', sprintf('%%%s%%', $search))
                    ->orWhere('description', 'like', sprintf('%%%s%%', $search));
            })
            ->when($isDefault !== null && $isDefault !== '', function ($query) use ($isDefault): void {
                $query->where('is_default', (bool) $isDefault);
            })
            ->when($sort === 'permissions_count', function ($query): void {
                $query->withCount('permissions')->orderBy('permissions_count', 'desc');
            })
            ->when($sort === 'created_at', function ($query): void {
                $query->orderBy('created_at', 'desc');
            })
            ->when($sort === 'name', function ($query): void {
                $query->orderBy('name');
            })
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
            'filters' => [
                'search' => $search,
                'is_default' => $isDefault,
                'sort' => $sort,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        $permissions = Permission::query()
            ->select(['id', 'name', 'display_name', 'group'])
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group');

        return Inertia::render('admin/roles/create-edit', [
            'permissions' => $permissions,
            'mode' => 'create',
        ]);
    }

    public function store(StoreRoleRequest $request, StoreRoleAction $action): RedirectResponse
    {
        try {
            $action->execute($request->validated());

            return to_route('admin.roles.index')
                ->with('success', 'Role created successfully.');
        } catch (Exception $exception) {
            return back()
                ->withErrors(['error' => 'Failed to create role: '.$exception->getMessage()])
                ->withInput();
        }
    }

    public function show(Role $role): Response
    {
        $role->load('permissions');

        return Inertia::render('admin/roles/show', [
            'role' => $role,
        ]);
    }

    public function edit(Role $role): Response
    {
        $role->load('permissions');

        $permissions = Permission::query()
            ->select(['id', 'name', 'display_name', 'group'])
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group');

        return Inertia::render('admin/roles/create-edit', [
            'role' => $role,
            'permissions' => $permissions,
            'mode' => 'edit',
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role, UpdateRoleAction $action): RedirectResponse
    {
        // Prevent updating default roles
        if ($role->is_default) {
            return to_route('admin.roles.index')
                ->with('error', 'Default roles cannot be updated.');
        }

        try {
            $role = $action->execute($role, $request->validated());

            return to_route('admin.roles.index')
                ->with('success', 'Role updated successfully.');
        } catch (Exception $exception) {
            return back()
                ->withErrors(['error' => 'Failed to update role: '.$exception->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Role $role, DeleteRoleAction $action): RedirectResponse
    {
        try {
            $action->execute($role);

            return to_route('admin.roles.index')
                ->with('success', 'Role deleted successfully.');
        } catch (Exception $exception) {
            return to_route('admin.roles.index')
                ->with('error', 'Failed to delete role: '.$exception->getMessage());
        }
    }
}
