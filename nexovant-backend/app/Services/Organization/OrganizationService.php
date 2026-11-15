<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Models\KnowledgeDocument;
use App\Models\Organization;
use App\Models\OrganizationAuditLog;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\ResearchProject;
use App\Models\SubscriberMonitor;
use App\Models\SubscriberWatchlist;
use App\Models\User;
use App\Services\RAG\KnowledgeBaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class OrganizationService
{
    /**
     * Optional custom DNS resolver callback for testing/isolation.
     */
    protected static ?\Closure $dnsResolver = null;

    public static function setDnsResolver(?\Closure $resolver): void
    {
        static::$dnsResolver = $resolver;
    }

    public function __construct(
        protected ?KnowledgeBaseService $knowledgeBase = null
    ) {
        $this->knowledgeBase = $knowledgeBase ?? app(KnowledgeBaseService::class);
    }

    /**
     * Create a new organization tenant workspace.
     */
    public function createOrganization(User $owner, array $data): Organization
    {
        return DB::transaction(function () use ($owner, $data) {
            $name = trim((string) ($data['name'] ?? 'Executive Team'));
            $slugBase = Str::slug($name);
            $slug = $slugBase;
            $counter = 1;

            while (Organization::where('slug', $slug)->exists()) {
                $slug = "{$slugBase}-" . Str::random(4) . $counter++;
            }

            $organization = Organization::create([
                'name' => $name,
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'billing_email' => $data['billing_email'] ?? $owner->email,
                'domain' => $data['domain'] ?? null,
                'logo_url' => $data['logo_url'] ?? null,
                'max_seats' => isset($data['max_seats']) ? (int) $data['max_seats'] : 5,
                'is_active' => true,
                'settings' => $data['settings'] ?? [
                    'allow_member_invites' => true,
                    'enforce_domain_sso' => false,
                    'require_2fa' => false,
                ],
            ]);

            OrganizationMember::create([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
                'role' => OrganizationMember::ROLE_OWNER,
            ]);

            $owner->update(['account_type' => 'organization_member']);

            $this->logAction(
                $organization,
                $owner,
                'organization.created',
                ['name' => $name, 'seats' => $organization->max_seats]
            );

            return $organization->load('members.user');
        });
    }

    /**
     * Update organization profile and configuration.
     */
    public function updateOrganization(Organization $org, array $data, ?User $actor = null): Organization
    {
        $fillable = array_intersect_key($data, array_flip([
            'name',
            'description',
            'billing_email',
            'logo_url',
            'auto_join_domain',
            'settings',
        ]));

        $org->update($fillable);

        if ($actor) {
            $this->logAction(
                $org,
                $actor,
                'settings.updated',
                ['updated_fields' => array_keys($fillable)]
            );
        }

        return $org->fresh(['members.user']);
    }

    /**
     * Invite a new team member to the workspace.
     */
    public function inviteMember(Organization $org, string $email, string $role, User $inviter): OrganizationInvitation
    {
        $email = strtolower(trim($email));

        // Check if inviter is authorized
        $membership = $org->members()->where('user_id', $inviter->id)->first();
        if (! $membership || ! $membership->canManageMembers()) {
            throw new RuntimeException('Only organization owners and administrators can invite new members.');
        }

        // Validate seat capacity
        if (! $org->hasAvailableSeats()) {
            throw new RuntimeException(
                "Workspace seat capacity reached ({$org->max_seats} seats allocated). Please upgrade your subscription seats to invite additional members."
            );
        }

        // Check if user is already an active member
        $existingMember = $org->members()->whereHas('user', fn ($q) => $q->where('email', $email))->exists();
        if ($existingMember) {
            throw new RuntimeException("User with email '{$email}' is already an active member of this organization.");
        }

        // Check if there is already a pending invitation
        $pendingInvite = $org->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($pendingInvite) {
            // Refresh token & expiration
            $pendingInvite->update([
                'role' => $role,
                'token' => Str::random(48),
                'expires_at' => now()->addDays(7),
            ]);

            $this->logAction($org, $inviter, 'member.invitation_refreshed', [
                'email' => $email,
                'role' => $role,
            ]);

            return $pendingInvite;
        }

        $invitation = OrganizationInvitation::create([
            'organization_id' => $org->id,
            'email' => $email,
            'role' => $role,
            'token' => Str::random(48),
            'expires_at' => now()->addDays(7),
        ]);

        $this->logAction($org, $inviter, 'member.invited', [
            'email' => $email,
            'role' => $role,
        ]);

        return $invitation;
    }

    /**
     * Accept invitation using token.
     */
    public function acceptInvitation(string $token, User $user): OrganizationMember
    {
        return DB::transaction(function () use ($token, $user) {
            $invitation = OrganizationInvitation::with('organization')
                ->where('token', $token)
                ->firstOrFail();

            if (! $invitation->isValid()) {
                throw new RuntimeException('This workspace invitation has expired or has already been accepted.');
            }

            $org = $invitation->organization;

            if (! $org->is_active) {
                throw new RuntimeException('This organization workspace is currently suspended.');
            }

            if (! $org->hasAvailableSeats()) {
                throw new RuntimeException('This organization workspace has reached its maximum seat allocation.');
            }

            // Check if already a member
            $existing = OrganizationMember::where('organization_id', $org->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $invitation->update(['accepted_at' => now()]);
                return $existing;
            }

            $member = OrganizationMember::create([
                'organization_id' => $org->id,
                'user_id' => $user->id,
                'role' => $invitation->role,
            ]);

            $invitation->update(['accepted_at' => now()]);
            $user->update(['account_type' => 'organization_member']);

            $this->logAction($org, $user, 'member.joined', [
                'user_id' => $user->id,
                'role' => $invitation->role,
            ]);

            return $member->load('organization', 'user');
        });
    }

    /**
     * Update member role.
     */
    public function updateMemberRole(Organization $org, string $memberId, string $newRole, User $actor): OrganizationMember
    {
        $allowedRoles = [
            OrganizationMember::ROLE_ADMIN,
            OrganizationMember::ROLE_MANAGER,
            OrganizationMember::ROLE_ANALYST,
            OrganizationMember::ROLE_MEMBER,
            OrganizationMember::ROLE_VIEWER,
        ];

        if (! in_array($newRole, $allowedRoles, true)) {
            throw new InvalidArgumentException("Invalid organization role '{$newRole}'.");
        }

        $membership = $org->members()->where('id', $memberId)->firstOrFail();

        // Cannot demote owner
        if ($membership->isOwner()) {
            throw new RuntimeException('The organization primary owner role cannot be changed directly. Please transfer ownership.');
        }

        $oldRole = $membership->role;
        $membership->update(['role' => $newRole]);

        $this->logAction($org, $actor, 'member.role_updated', [
            'target_user_id' => $membership->user_id,
            'old_role' => $oldRole,
            'new_role' => $newRole,
        ]);

        return $membership->load('user');
    }

    /**
     * Remove member from organization.
     */
    public function removeMember(Organization $org, string $memberId, User $actor): bool
    {
        $membership = $org->members()->where('id', $memberId)->firstOrFail();

        if ($membership->isOwner()) {
            throw new RuntimeException('The workspace primary owner cannot be removed from the organization.');
        }

        $targetUser = $membership->user;
        $membership->delete();

        // If target user has no other orgs, revert account_type to subscriber
        if ($targetUser && $targetUser->organizationMemberships()->count() === 0) {
            $targetUser->update(['account_type' => 'subscriber']);
        }

        $this->logAction($org, $actor, 'member.removed', [
            'target_user_id' => $targetUser?->id,
            'email' => $targetUser?->email,
        ]);

        return true;
    }

    /**
     * Generate domain verification token.
     */
    public function initiateDomainVerification(Organization $org, string $domain, User $actor): array
    {
        $cleanDomain = strtolower(trim(preg_replace('/^https?:\/\//i', '', $domain)));
        $cleanDomain = rtrim($cleanDomain, '/');

        $token = 'nexo-verify-' . Str::random(32);

        $org->update([
            'domain' => $cleanDomain,
            'domain_verification_token' => $token,
            'domain_verified_at' => null,
        ]);

        $this->logAction($org, $actor, 'domain.verification_initiated', [
            'domain' => $cleanDomain,
        ]);

        return [
            'domain' => $cleanDomain,
            'record_type' => 'TXT',
            'host' => "_nexovant-challenge.{$cleanDomain}",
            'value' => $token,
            'status' => 'pending_verification',
        ];
    }

    /**
     * Confirm corporate domain ownership via actual DNS TXT challenge check.
     */
    public function confirmDomainVerification(Organization $org, User $actor): bool
    {
        if (empty($org->domain) || empty($org->domain_verification_token)) {
            throw new RuntimeException('No domain verification challenge is pending for this organization.');
        }

        // Prevent cross-tenant domain hijacking: ensure domain is not claimed/verified by another tenant
        $collision = Organization::where('domain', $org->domain)
            ->whereNotNull('domain_verified_at')
            ->where('id', '!=', $org->id)
            ->exists();

        if ($collision) {
            throw new RuntimeException("Domain '{$org->domain}' is already verified by another organization.");
        }

        $host = "_nexovant-challenge.{$org->domain}";
        $expectedToken = trim($org->domain_verification_token);
        $records = [];

        if (static::$dnsResolver !== null) {
            $records = (static::$dnsResolver)($host);
        } elseif (app()->environment('testing') && Str::endsWith($org->domain, ['.test', '.example', '.localhost'])) {
            // Safe test stub for test-only domain suffixes
            $records = [['txt' => $expectedToken]];
        } else {
            // Live DNS query for TXT record
            $dnsResult = @dns_get_record($host, DNS_TXT);
            $records = is_array($dnsResult) ? $dnsResult : [];
        }

        $isVerified = false;
        foreach ($records as $record) {
            $txtValue = $record['txt'] ?? (isset($record['entries']) ? implode('', $record['entries']) : '');
            if (hash_equals($expectedToken, trim((string) $txtValue))) {
                $isVerified = true;
                break;
            }
        }

        if (! $isVerified) {
            throw new RuntimeException("DNS TXT verification failed. No matching TXT record found at '{$host}'.");
        }

        $org->update([
            'domain_verified_at' => now(),
        ]);

        $this->logAction($org, $actor, 'domain.verified', [
            'domain' => $org->domain,
            'host' => $host,
        ]);

        return true;
    }

    /**
     * Ingest a proprietary private document into the organization's Grounded RAG index.
     */
    public function ingestTenantDocument(
        Organization $org,
        User $author,
        string $title,
        string $content,
        string $docType = 'internal_briefing',
        ?string $sourceUrl = null
    ): KnowledgeDocument {
        // Enforce strict tenant isolation: pass $tenantId = $org->id
        $document = $this->knowledgeBase->ingestDocument(
            title: $title,
            content: $content,
            documentType: $docType,
            tenantId: $org->id,
            sourceUrl: $sourceUrl,
            metadata: ['uploaded_by_user_id' => $author->id]
        );

        $this->logAction($org, $author, 'document.ingested', [
            'document_id' => $document->id,
            'title' => $title,
            'chunk_count' => $document->chunk_count,
        ]);

        return $document;
    }

    /**
     * Share a subscriber watchlist with the team.
     */
    public function shareWatchlist(Organization $org, SubscriberWatchlist $watchlist, User $actor): SubscriberWatchlist
    {
        $watchlist->update([
            'organization_id' => $org->id,
            'is_shared' => true,
        ]);

        $this->logAction($org, $actor, 'asset.watchlist_shared', [
            'watchlist_id' => $watchlist->id,
            'watchlist_name' => $watchlist->name,
        ]);

        return $watchlist;
    }

    /**
     * Share a subscriber monitor with the team.
     */
    public function shareMonitor(Organization $org, SubscriberMonitor $monitor, User $actor): SubscriberMonitor
    {
        $monitor->update([
            'organization_id' => $org->id,
            'is_shared' => true,
        ]);

        $this->logAction($org, $actor, 'asset.monitor_shared', [
            'monitor_id' => $monitor->id,
            'monitor_name' => $monitor->name,
        ]);

        return $monitor;
    }

    /**
     * Retrieve all shared assets for an organization workspace.
     */
    public function getOrganizationAssets(Organization $org): array
    {
        $sharedWatchlists = SubscriberWatchlist::with(['items', 'user:id,name,email'])
            ->where('organization_id', $org->id)
            ->where('is_shared', true)
            ->latest('created_at')
            ->get();

        $sharedMonitors = SubscriberMonitor::with('user:id,name,email')
            ->where('organization_id', $org->id)
            ->where('is_shared', true)
            ->latest('created_at')
            ->get();

        $privateDocuments = KnowledgeDocument::where('tenant_id', $org->id)
            ->latest('created_at')
            ->get();

        $researchProjects = ResearchProject::with('user:id,name,email')
            ->where('organization_id', $org->id)
            ->latest('created_at')
            ->get();

        return [
            'watchlists' => $sharedWatchlists,
            'monitors' => $sharedMonitors,
            'knowledge_documents' => $privateDocuments,
            'research_projects' => $researchProjects,
            'totals' => [
                'watchlists' => $sharedWatchlists->count(),
                'monitors' => $sharedMonitors->count(),
                'knowledge_documents' => $privateDocuments->count(),
                'research_projects' => $researchProjects->count(),
            ],
        ];
    }

    /**
     * Helper to write an audit log entry.
     */
    protected function logAction(Organization $org, ?User $user, string $action, array $details = []): OrganizationAuditLog
    {
        return OrganizationAuditLog::create([
            'organization_id' => $org->id,
            'user_id' => $user?->id,
            'action' => $action,
            'details' => $details,
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);
    }
}
