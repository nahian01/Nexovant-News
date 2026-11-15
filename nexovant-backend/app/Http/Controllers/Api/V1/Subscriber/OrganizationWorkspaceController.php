<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscriber;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\SubscriberMonitor;
use App\Models\SubscriberWatchlist;
use App\Services\Organization\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class OrganizationWorkspaceController
{
    public function __construct(
        protected OrganizationService $organizationService
    ) {}

    /**
     * Get details of user's active organization workspace.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();

        $membership = OrganizationMember::with([
            'organization.members.user:id,name,email,created_at',
            'organization.invitations',
            'organization.subscriptions.plan',
        ])
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            return response()->json([
                'success' => true,
                'organization' => null,
                'is_in_organization' => false,
                'message' => 'User is currently operating as an independent subscriber.',
            ]);
        }

        $org = $membership->organization;
        $activeSub = $org->activeSubscription();

        $recentLogs = $org->auditLogs()
            ->with('user:id,name,email')
            ->latest('created_at')
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'is_in_organization' => true,
            'current_role' => $membership->role,
            'permissions' => [
                'can_manage_members' => $membership->canManageMembers(),
                'can_manage_assets' => $membership->canManageAssets(),
                'is_owner' => $membership->isOwner(),
                'is_admin' => $membership->isAdmin(),
            ],
            'organization' => [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
                'description' => $org->description,
                'domain' => $org->domain,
                'domain_verified' => $org->isDomainVerified(),
                'domain_verified_at' => $org->domain_verified_at?->format('c'),
                'billing_email' => $org->billing_email,
                'logo_url' => $org->logo_url,
                'is_active' => $org->is_active,
                'max_seats' => $org->max_seats,
                'used_seats' => $org->usedSeatsCount(),
                'available_seats' => $org->availableSeatsCount(),
                'has_available_seats' => $org->hasAvailableSeats(),
                'settings' => $org->settings ?? [],
                'members' => $org->members->map(function ($m) {
                    return [
                        'id' => $m->id,
                        'user_id' => $m->user_id,
                        'name' => $m->user?->name ?? 'Unknown',
                        'email' => $m->user?->email ?? '',
                        'role' => $m->role,
                        'joined_at' => $m->created_at->format('c'),
                    ];
                }),
                'pending_invitations' => $org->invitations()
                    ->whereNull('accepted_at')
                    ->where('expires_at', '>', now())
                    ->get()
                    ->map(fn ($inv) => [
                        'id' => $inv->id,
                        'email' => $inv->email,
                        'role' => $inv->role,
                        'token' => $inv->token,
                        'expires_at' => $inv->expires_at->format('c'),
                        'created_at' => $inv->created_at->format('c'),
                    ]),
                'subscription' => $activeSub ? [
                    'id' => $activeSub->id,
                    'plan_name' => $activeSub->plan?->name,
                    'plan_slug' => $activeSub->plan?->slug,
                    'status' => $activeSub->status,
                    'renews_at' => $activeSub->renews_at?->format('c'),
                ] : null,
                'recent_audit_logs' => $recentLogs->map(fn ($log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'actor_name' => $log->user?->name ?? 'System',
                    'details' => $log->details,
                    'created_at' => $log->created_at->format('c'),
                ]),
            ],
        ]);
    }

    /**
     * Create an organization workspace.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'billing_email' => ['nullable', 'email'],
            'domain' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        // Check if user already owns or is in an organization
        if ($user->organizationMemberships()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You are already a member of an existing organization workspace.',
            ], 422);
        }

        try {
            $org = $this->organizationService->createOrganization($user, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Organization workspace successfully established.',
                'organization' => $org,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update workspace settings.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $membership = OrganizationMember::where('user_id', $user->id)->firstOrFail();

        if (! $membership->canManageMembers()) {
            return response()->json([
                'success' => false,
                'message' => 'Only workspace owners and administrators can modify settings.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'billing_email' => ['sometimes', 'email'],
            'logo_url' => ['nullable', 'url'],
            'auto_join_domain' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
        ]);

        try {
            $org = $this->organizationService->updateOrganization(
                $membership->organization,
                $validated,
                $user
            );

            return response()->json([
                'success' => true,
                'message' => 'Organization settings updated successfully.',
                'organization' => $org,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Invite a colleague to the organization.
     */
    public function invite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'in:admin,manager,analyst,member,viewer'],
        ]);

        $user = $request->user();
        $membership = OrganizationMember::where('user_id', $user->id)->firstOrFail();

        if (! $membership->canManageMembers()) {
            return response()->json([
                'success' => false,
                'message' => 'Only organization owners and administrators can invite new members.',
            ], 403);
        }

        try {
            $invitation = $this->organizationService->inviteMember(
                $membership->organization,
                $validated['email'],
                $validated['role'],
                $user
            );

            return response()->json([
                'success' => true,
                'message' => "Workspace invitation sent to {$validated['email']}.",
                'invitation' => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'token' => $invitation->token,
                    'expires_at' => $invitation->expires_at->format('c'),
                ],
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * List pending invitations.
     */
    public function invitations(Request $request): JsonResponse
    {
        $user = $request->user();
        $membership = OrganizationMember::where('user_id', $user->id)->firstOrFail();

        $invites = $membership->organization->invitations()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'invitations' => $invites,
        ]);
    }

    /**
     * Revoke a pending invitation.
     */
    public function revokeInvitation(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $membership = OrganizationMember::where('user_id', $user->id)->firstOrFail();

        if (! $membership->canManageMembers()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $invitation = $membership->organization->invitations()->where('id', $id)->firstOrFail();
        $invitation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Invitation revoked successfully.',
        ]);
    }

    /**
     * Accept a workspace invitation.
     */
    public function acceptInvitation(Request $request, string $token): JsonResponse
    {
        $user = $request->user();

        try {
            $member = $this->organizationService->acceptInvitation($token, $user);

            return response()->json([
                'success' => true,
                'message' => "Successfully joined {$member->organization->name}.",
                'member' => $member,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update a member's role.
     */
    public function updateMemberRole(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:admin,manager,analyst,member,viewer'],
        ]);

        $user = $request->user();
        $membership = OrganizationMember::where('user_id', $user->id)->firstOrFail();

        if (! $membership->canManageMembers()) {
            return response()->json([
                'success' => false,
                'message' => 'Only workspace owners and administrators can change member roles.',
            ], 403);
        }

        try {
            $updated = $this->organizationService->updateMemberRole(
                $membership->organization,
                $id,
                $validated['role'],
                $user
            );

            return response()->json([
                'success' => true,
                'message' => 'Member role updated successfully.',
                'member' => $updated,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove a member from the workspace.
     */
    public function removeMember(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $membership = OrganizationMember::where('user_id', $user->id)->firstOrFail();

        if (! $membership->canManageMembers()) {
            return response()->json([
                'success' => false,
                'message' => 'Only workspace owners and administrators can remove members.',
            ], 403);
        }

        try {
            $this->organizationService->removeMember(
                $membership->organization,
                $id,
                $user
            );

            return response()->json([
                'success' => true,
                'message' => 'Member removed from organization workspace.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Initiate domain verification.
     */
    public function initiateDomain(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $membership = OrganizationMember::where('user_id', $user->id)->firstOrFail();

        if (! $membership->canManageMembers()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $challenge = $this->organizationService->initiateDomainVerification(
            $membership->organization,
            $validated['domain'],
            $user
        );

        return response()->json([
            'success' => true,
            'challenge' => $challenge,
        ]);
    }

    /**
     * Confirm domain verification.
     */
    public function confirmDomain(Request $request): JsonResponse
    {
        $user = $request->user();
        $membership = OrganizationMember::where('user_id', $user->id)->firstOrFail();

        if (! $membership->canManageMembers()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        try {
            $this->organizationService->confirmDomainVerification($membership->organization, $user);

            return response()->json([
                'success' => true,
                'message' => 'Domain successfully verified and authenticated for this organization.',
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get shared team assets.
     */
    public function assets(Request $request): JsonResponse
    {
        $user = $request->user();
        $membership = OrganizationMember::where('user_id', $user->id)->firstOrFail();

        $assets = $this->organizationService->getOrganizationAssets($membership->organization);

        return response()->json([
            'success' => true,
            'assets' => $assets,
        ]);
    }

    /**
     * Share a subscriber watchlist with the workspace team.
     */
    public function shareWatchlist(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $membership = OrganizationMember::where('user_id', $user->id)->firstOrFail();

        if (! $membership->canManageAssets()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to share workspace assets.'], 403);
        }

        $watchlist = SubscriberWatchlist::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $shared = $this->organizationService->shareWatchlist($membership->organization, $watchlist, $user);

        return response()->json([
            'success' => true,
            'message' => "Watchlist '{$watchlist->name}' is now shared with the team workspace.",
            'watchlist' => $shared,
        ]);
    }

    /**
     * Share a subscriber custom monitor with the workspace team.
     */
    public function shareMonitor(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $membership = OrganizationMember::where('user_id', $user->id)->firstOrFail();

        if (! $membership->canManageAssets()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to share workspace assets.'], 403);
        }

        $monitor = SubscriberMonitor::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $shared = $this->organizationService->shareMonitor($membership->organization, $monitor, $user);

        return response()->json([
            'success' => true,
            'message' => "Monitor '{$monitor->name}' is now shared with the team workspace.",
            'monitor' => $shared,
        ]);
    }

    /**
     * Upload/ingest a private organizational document into Grounded RAG index with strict tenant isolation.
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'min:20'],
            'document_type' => ['nullable', 'string', 'max:50'],
            'source_url' => ['nullable', 'url'],
        ]);

        $user = $request->user();
        $membership = OrganizationMember::where('user_id', $user->id)->firstOrFail();

        if (! $membership->canManageAssets()) {
            return response()->json(['success' => false, 'message' => 'Only analysts and administrators can upload private documents.'], 403);
        }

        try {
            $doc = $this->organizationService->ingestTenantDocument(
                $membership->organization,
                $user,
                $validated['title'],
                $validated['content'],
                $validated['document_type'] ?? 'internal_briefing',
                $validated['source_url'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Document indexed and vectorized into private organizational knowledge base.',
                'document' => [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'document_type' => $doc->document_type,
                    'chunk_count' => $doc->chunk_count,
                    'tenant_id' => $doc->tenant_id,
                    'created_at' => $doc->created_at->format('c'),
                ],
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get organization audit logs.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $user = $request->user();
        $membership = OrganizationMember::where('user_id', $user->id)->firstOrFail();

        $logs = $membership->organization->auditLogs()
            ->with('user:id,name,email')
            ->latest('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'audit_logs' => $logs,
        ]);
    }

    /**
     * Leave the organization workspace.
     */
    public function leave(Request $request): JsonResponse
    {
        $user = $request->user();
        $membership = OrganizationMember::where('user_id', $user->id)->firstOrFail();

        if ($membership->isOwner()) {
            return response()->json([
                'success' => false,
                'message' => 'The organization owner cannot leave without transferring ownership or deleting the workspace.',
            ], 422);
        }

        $membership->delete();

        if ($user->organizationMemberships()->count() === 0) {
            $user->update(['account_type' => 'subscriber']);
        }

        return response()->json([
            'success' => true,
            'message' => 'You have departed the organization workspace.',
        ]);
    }
}
