<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

final class DashboardController extends Controller
{
    public function dashboard()
    {
        return Inertia::render('admin/dashboard');
    }

    /**
     * Get basic dashboard statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $userStats = [];
        $roleStats = [];

        // Check if user has permission to view user stats
        if ($request->user()->can('users.view')) {
            $totalUsers = User::query()->count();
            $activeUsers = User::query()->whereNotNull('email_verified_at')->count();
            $newUsersToday = User::query()->whereDate('created_at', today())->count();
            $verificationRate = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 0;

            $userStats = [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'new_users_today' => $newUsersToday,
                'verification_rate' => $verificationRate,
            ];
        }

        // Check if user has permission to view role stats
        if ($request->user()->can('roles.view')) {
            $totalRoles = Role::query()->count();
            $totalPermissions = Permission::query()->count();

            $roleStats = [
                'total_roles' => $totalRoles,
                'total_permissions' => $totalPermissions,
            ];
        }

        return response()->json(array_merge($userStats, $roleStats));
    }
}
