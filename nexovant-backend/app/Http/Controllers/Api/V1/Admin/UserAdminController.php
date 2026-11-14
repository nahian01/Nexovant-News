<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserAdminController
{
    /**
     * List platform users with pagination, subscription, roles, and search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with(['subscription.plan', 'roles']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('account_type')) {
            $query->where('account_type', $type);
        }

        if ($roleName = $request->input('role')) {
            $query->whereHas('roles', function ($q) use ($roleName) {
                $q->where('name', $roleName);
            });
        }

        $users = $query->latest('created_at')->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'meta' => [
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
            'roles' => Role::all(),
            'organizations' => Organization::withCount('members')->get(),
        ]);
    }

    /**
     * Show single user profile with subscriptions, saved articles, and audit activity.
     */
    public function show(string $id): JsonResponse
    {
        $user = User::with([
            'subscription.plan.entitlements',
            'roles.permissions',
            'savedArticles',
            'preferences',
        ])->findOrFail($id);

        $activity = AuditLog::where('user_id', $user->id)
            ->latest('created_at')
            ->limit(15)
            ->get();

        return response()->json([
            'success' => true,
            'user' => $user,
            'activity' => $activity,
        ]);
    }

    /**
     * Toggle active/suspended account status.
     * Protects the last active Super Admin from accidental suspension.
     */
    public function toggleStatus(string $id, Request $request): JsonResponse
    {
        $admin = $request->user();
        $user = User::findOrFail($id);

        if ($user->hasRole('super_admin') && $user->is_active) {
            $activeSuperAdmins = User::where('is_active', true)
                ->where(function ($q) {
                    $q->where('account_type', 'admin')
                      ->orWhereHas('roles', fn($r) => $r->where('name', 'super_admin'));
                })
                ->count();

            if ($activeSuperAdmins <= 1) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'CANNOT_SUSPEND_LAST_SUPER_ADMIN',
                        'message' => 'Protection lock: Cannot deactivate the platform’s last active Super Administrator.',
                    ]
                ], 422);
            }
        }

        $user->is_active = !$user->is_active;
        $user->save();

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'user.status_toggled',
            'resource_type' => 'User',
            'resource_id' => $user->id,
            'new_values' => ['is_active' => $user->is_active],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "User '{$user->email}' status changed to " . ($user->is_active ? 'Active' : 'Suspended') . '.',
            'is_active' => $user->is_active,
        ]);
    }

    /**
     * Update user platform role and assign roles.
     * Protects the last active Super Admin from accidental demotion.
     */
    public function updateRole(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'account_type' => ['sometimes', 'string', 'in:individual,organization_member,admin,editorial_admin,support_admin'],
            'role_ids' => ['sometimes', 'array'],
            'role_name' => ['sometimes', 'string'],
        ]);

        $admin = $request->user();
        $user = User::findOrFail($id);

        if ($user->hasRole('super_admin') && isset($validated['account_type']) && $validated['account_type'] !== 'admin') {
            $superAdminsCount = User::whereHas('roles', fn($r) => $r->where('name', 'super_admin'))->count();
            if ($superAdminsCount <= 1) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'CANNOT_DEMOTE_LAST_SUPER_ADMIN',
                        'message' => 'Protection lock: Cannot remove administrator status from the last Super Admin.',
                    ]
                ], 422);
            }
        }

        if (isset($validated['account_type'])) {
            $user->account_type = $validated['account_type'];
            $user->save();
        }

        if (isset($validated['role_ids'])) {
            $user->roles()->sync($validated['role_ids']);
        } elseif (isset($validated['role_name'])) {
            $role = Role::where('name', $validated['role_name'])->first();
            if ($role) {
                $user->roles()->sync([$role->id]);
            }
        }

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'user.roles_updated',
            'resource_type' => 'User',
            'resource_id' => $user->id,
            'new_values' => $validated,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "User '{$user->email}' account privileges updated.",
            'user' => $user->load('roles'),
        ]);
    }

    /**
     * Reset password for a user.
     */
    public function resetPassword(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $admin = $request->user();
        $user = User::findOrFail($id);
        $user->password = Hash::make($validated['password']);
        $user->save();

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'user.password_reset_by_admin',
            'resource_type' => 'User',
            'resource_id' => $user->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Password reset for user '{$user->email}'.",
        ]);
    }

    /**
     * List all platform roles and permissions.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();

        return response()->json([
            'success' => true,
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }
}
