<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\User\DeleteUserAction;
use App\Actions\User\StoreUserAction;
use App\Actions\User\SuspendUserAction;
use App\Actions\User\UnsuspendUserAction;
use App\Actions\User\UpdateUserAction;
use App\Actions\User\VerifyEmailAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $status = $request->get('status');
        $role = $request->get('role');
        $sort = $request->get('sort', 'name');

        $validSorts = ['name', 'email', 'created_at', 'roles_count'];
        $sort = in_array($sort, $validSorts) ? $sort : 'name';

        $users = User::query()
            ->select(['id', 'name', 'email', 'email_verified_at', 'status', 'status_reason', 'status_updated_at', 'created_at'])
            ->with(['roles:id,name,display_name'])
            ->when($search, function ($query, $search): void {
                $query
                    ->where('name', 'like', sprintf('%%%s%%', $search))
                    ->orWhere('email', 'like', sprintf('%%%s%%', $search));
            })
            ->when($status === 'verified', function ($query): void {
                $query->whereNotNull('email_verified_at');
            })
            ->when($status === 'unverified', function ($query): void {
                $query->whereNull('email_verified_at');
            })
            ->when($role && $role !== 'all', function ($query) use ($role): void {
                $query->whereHas('roles', function ($q) use ($role): void {
                    $q->where('name', $role);
                });
            })
            ->when($sort === 'roles_count', function ($query): void {
                $query->withCount('roles')->orderBy('roles_count', 'desc');
            })
            ->when($sort === 'created_at', function ($query): void {
                $query->orderBy('created_at', 'desc');
            })
            ->when($sort === 'email', function ($query): void {
                $query->orderBy('email');
            })
            ->when($sort === 'name', function ($query): void {
                $query->orderBy('name');
            })
            ->paginate($perPage)
            ->withQueryString();

        $roles = Role::query()
            ->select(['id', 'name', 'display_name'])
            ->orderBy('display_name')
            ->get();

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'role' => $role,
                'sort' => $sort,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(StoreUserRequest $request, StoreUserAction $action): RedirectResponse
    {
        try {
            $action->execute($request->validated());

            return to_route('admin.users.index')
                ->with('success', 'User created successfully.');
        } catch (Exception $exception) {
            return back()
                ->withErrors(['error' => 'Failed to create user: '.$exception->getMessage()])
                ->withInput();
        }
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => $user->load('roles.permissions:id,name'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): RedirectResponse
    {
        try {
            $user = $action->execute($user, $request->validated());

            return to_route('admin.users.index')
                ->with('success', 'User updated successfully.');
        } catch (Exception $exception) {
            return back()
                ->withErrors(['error' => 'Failed to update user: '.$exception->getMessage()])
                ->withInput();
        }
    }

    public function destroy(User $user, DeleteUserAction $action): RedirectResponse
    {
        try {
            $action->execute($user);

            return to_route('admin.users.index')
                ->with('success', 'User deleted successfully.');
        } catch (Exception) {
            return to_route('admin.users.index')
                ->with('error', 'Cannot delete this user.');
        }
    }

    public function verifyEmail(User $user, VerifyEmailAction $action): RedirectResponse
    {
        try {
            $action->execute($user);

            return back()->with('success', 'User email verified successfully.');
        } catch (Exception $exception) {
            return back()->with('error', 'Failed to verify email: '.$exception->getMessage());
        }
    }

    public function suspend(Request $request, User $user, SuspendUserAction $action): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $action->execute($user, $request->input('reason'));

            return back()->with('success', 'User suspended successfully.');
        } catch (Exception $exception) {
            return back()->with('error', 'Failed to suspend user: '.$exception->getMessage());
        }
    }

    public function unsuspend(User $user, UnsuspendUserAction $action): RedirectResponse
    {
        try {
            $action->execute($user);

            return back()->with('success', 'User unsuspend successfully.');
        } catch (Exception $exception) {
            return back()->with('error', 'Failed to unsuspend user: '.$exception->getMessage());
        }
    }
}
