<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrganizationController
{
    /**
     * Get details of user's active organization workspace.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();

        $membership = OrganizationMember::with('organization.members.user')
            ->where('user_id', $user->id)
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => true,
                'organization' => null,
                'message' => 'User is currently on an individual executive subscription.',
            ]);
        }

        return response()->json([
            'success' => true,
            'organization' => $membership->organization,
            'current_role' => $membership->role,
        ]);
    }

    /**
     * Create an organization workspace.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email'],
        ]);

        $user = $request->user();

        $organization = Organization::create([
            'name' => $request->input('name'),
            'slug' => Str::slug($request->input('name')) . '-' . Str::random(5),
            'billing_email' => $request->input('billing_email') ?? $user->email,
            'max_seats' => 5,
            'is_active' => true,
        ]);

        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $user->update(['account_type' => 'organization_member']);

        return response()->json([
            'success' => true,
            'message' => 'Organization workspace established.',
            'organization' => $organization->load('members.user'),
        ], 201);
    }

    /**
     * Invite a team member to the workspace.
     */
    public function invite(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'in:admin,manager,member,viewer'],
        ]);

        $user = $request->user();
        $membership = OrganizationMember::where('user_id', $user->id)->firstOrFail();

        // Check if inviter has permission
        if (!in_array($membership->role, ['owner', 'admin'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INSUFFICIENT_PERMISSIONS',
                    'message' => 'Only organization owners and admins can invite team members.',
                ]
            ], 403);
        }

        $invitation = OrganizationInvitation::create([
            'organization_id' => $membership->organization_id,
            'email' => $request->input('email'),
            'role' => $request->input('role'),
            'token' => Str::random(48),
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Team invitation generated and dispatched.',
            'invitation' => $invitation,
        ], 201);
    }
}
