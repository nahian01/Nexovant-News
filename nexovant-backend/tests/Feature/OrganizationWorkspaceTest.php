<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KnowledgeDocument;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\PlanEntitlement;
use App\Models\SubscriberMonitor;
use App\Models\SubscriberWatchlist;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use App\Services\Organization\OrganizationService;
use App\Services\RAG\GroundedRagService;
use App\Services\RAG\KnowledgeBaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic plans for entitlement tests
        $freePlan = Plan::firstOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free Tier',
                'description' => 'Free individual access',
                'price_monthly_usd' => 0,
                'price_annual_usd' => 0,
                'price_monthly_bdt' => 0,
                'price_annual_bdt' => 0,
                'is_active' => true,
            ]
        );

        $execPlan = Plan::firstOrCreate(
            ['slug' => 'executive'],
            [
                'name' => 'Executive Enterprise',
                'description' => 'Enterprise workspace intelligence',
                'price_monthly_usd' => 49,
                'price_annual_usd' => 490,
                'price_monthly_bdt' => 4999,
                'price_annual_bdt' => 49990,
                'is_active' => true,
            ]
        );

        PlanEntitlement::firstOrCreate(
            ['plan_id' => $execPlan->id, 'feature_key' => EntitlementService::FEATURE_PDF_DOWNLOAD],
            ['value' => 'true', 'value_type' => 'boolean']
        );
        PlanEntitlement::firstOrCreate(
            ['plan_id' => $execPlan->id, 'feature_key' => EntitlementService::FEATURE_AI_DEEP_RESEARCH],
            ['value' => 'true', 'value_type' => 'boolean']
        );
        PlanEntitlement::firstOrCreate(
            ['plan_id' => $execPlan->id, 'feature_key' => EntitlementService::FEATURE_MAX_WATCHLISTS],
            ['value' => '50', 'value_type' => 'integer']
        );
    }

    public function test_subscriber_can_create_organization_workspace(): void
    {
        $owner = User::factory()->create(['account_type' => 'subscriber']);

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organization', [
            'name' => 'Apex Capital Partners',
            'description' => 'Global technology investment syndicate',
            'domain' => 'apexcapital.com',
            'billing_email' => 'finance@apexcapital.com',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Organization workspace successfully established.',
            ]);

        $this->assertDatabaseHas('organizations', [
            'name' => 'Apex Capital Partners',
            'domain' => 'apexcapital.com',
            'billing_email' => 'finance@apexcapital.com',
            'max_seats' => 5,
        ]);

        $this->assertDatabaseHas('organization_members', [
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $owner->refresh();
        $this->assertEquals('organization_member', $owner->account_type);
    }

    public function test_owner_can_invite_team_colleague(): void
    {
        $owner = User::factory()->create();
        $org = Organization::create([
            'name' => 'Vanguard Intelligence',
            'slug' => 'vanguard-intel',
            'max_seats' => 3,
            'is_active' => true,
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organization/invite', [
            'email' => 'analyst1@vanguard.com',
            'role' => 'analyst',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $org->id,
            'email' => 'analyst1@vanguard.com',
            'role' => 'analyst',
        ]);
    }

    public function test_seat_quota_enforcement_blocks_invitation_when_capacity_reached(): void
    {
        $owner = User::factory()->create();
        $org = Organization::create([
            'name' => 'Micro Team',
            'slug' => 'micro-team',
            'max_seats' => 1, // Only 1 seat allowed
            'is_active' => true,
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        // Attempting to invite when seats are already fully utilized (1/1)
        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organization/invite', [
            'email' => 'overflow@micro.com',
            'role' => 'member',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString('seat capacity reached', $response->json('message'));
        $this->assertDatabaseMissing('organization_invitations', [
            'organization_id' => $org->id,
            'email' => 'overflow@micro.com',
        ]);
    }

    public function test_colleague_can_accept_workspace_invitation(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@corp.com', 'account_type' => 'subscriber']);

        $org = Organization::create([
            'name' => 'Stratagem Labs',
            'slug' => 'stratagem-labs',
            'max_seats' => 5,
            'is_active' => true,
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $invitation = OrganizationInvitation::create([
            'organization_id' => $org->id,
            'email' => 'invitee@corp.com',
            'role' => 'analyst',
            'token' => 'secure-token-1234567890',
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($invitee, 'sanctum')->postJson("/api/v1/organization/invitations/{$invitation->token}/accept");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $org->id,
            'user_id' => $invitee->id,
            'role' => 'analyst',
        ]);

        $invitation->refresh();
        $this->assertNotNull($invitation->accepted_at);

        $invitee->refresh();
        $this->assertEquals('organization_member', $invitee->account_type);
    }

    public function test_role_authorization_prevents_viewer_from_inviting(): void
    {
        $viewer = User::factory()->create();
        $org = Organization::create([
            'name' => 'Restricted Org',
            'slug' => 'restricted-org',
            'max_seats' => 10,
            'is_active' => true,
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
        ]);

        $response = $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/organization/invite', [
            'email' => 'newuser@org.com',
            'role' => 'analyst',
        ]);

        $response->assertStatus(403);
    }

    public function test_workspace_owner_cannot_be_demoted_or_removed(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $org = Organization::create([
            'name' => 'Governance Org',
            'slug' => 'gov-org',
            'max_seats' => 5,
            'is_active' => true,
        ]);

        $ownerMembership = OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        // Admin attempts to change owner's role
        $respDemote = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/organization/members/{$ownerMembership->id}/role", [
            'role' => 'analyst',
        ]);
        $respDemote->assertStatus(422);

        // Admin attempts to remove owner
        $respRemove = $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/organization/members/{$ownerMembership->id}");
        $respRemove->assertStatus(422);

        $this->assertDatabaseHas('organization_members', [
            'id' => $ownerMembership->id,
            'role' => 'owner',
        ]);
    }

    public function test_team_member_can_share_watchlist_with_organization(): void
    {
        $user = User::factory()->create();
        $org = Organization::create([
            'name' => 'Research Syndicate',
            'slug' => 'research-syndicate',
            'max_seats' => 5,
            'is_active' => true,
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'analyst',
        ]);

        $watchlist = SubscriberWatchlist::create([
            'user_id' => $user->id,
            'name' => 'Quantum Computing Competitors',
            'description' => 'Top 10 quantum hardware developers',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/organization/assets/watchlists/{$watchlist->id}/share");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $watchlist->refresh();
        $this->assertEquals($org->id, $watchlist->organization_id);
        $this->assertTrue($watchlist->is_shared);

        // Verify assets listing
        $assetsResp = $this->actingAs($user, 'sanctum')->getJson('/api/v1/organization/assets');
        $assetsResp->assertStatus(200)
            ->assertJsonPath('assets.totals.watchlists', 1);
    }

    public function test_grounded_rag_strict_multi_tenant_isolation(): void
    {
        $orgA = Organization::create(['name' => 'Tenant Alpha', 'slug' => 'tenant-alpha', 'is_active' => true]);
        $orgB = Organization::create(['name' => 'Tenant Beta', 'slug' => 'tenant-beta', 'is_active' => true]);

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $userA->id, 'role' => 'analyst']);
        OrganizationMember::create(['organization_id' => $orgB->id, 'user_id' => $userB->id, 'role' => 'analyst']);

        $orgService = app(OrganizationService::class);
        $ragService = app(GroundedRagService::class);

        // 1. Tenant Alpha ingests top-secret internal briefing
        $alphaSecretDoc = $orgService->ingestTenantDocument(
            org: $orgA,
            author: $userA,
            title: 'Project Apex Stealth Acquisition',
            content: 'Project Apex stealth target is Silicon Photonics microchip startup valued at 450 million USD in Zurich.',
            docType: 'confidential_mna'
        );

        $this->assertEquals($orgA->id, $alphaSecretDoc->tenant_id);

        // 2. User A queries Grounded RAG with Tenant Alpha context
        $resultA = $ragService->query(
            queryText: 'What is the stealth target valuation of Project Apex?',
            tenantId: $orgA->id,
            userId: $userA->id
        );

        $this->assertTrue($resultA['success']);
        $this->assertGreaterThan(0, count($resultA['source_citations']));
        $this->assertEquals($alphaSecretDoc->id, $resultA['source_citations'][0]['document_id']);

        // 3. User B (Tenant Beta) queries the EXACT same confidential query
        $resultB = $ragService->query(
            queryText: 'What is the stealth target valuation of Project Apex?',
            tenantId: $orgB->id,
            userId: $userB->id
        );

        // Crucial check: Tenant B must get ZERO citations from Tenant A's private document!
        $this->assertTrue($resultB['success']);
        $this->assertCount(0, $resultB['source_citations']);
        $this->assertStringContainsString('No verified intelligence records were found', $resultB['response']);

        // 4. Public subscriber (tenant_id = null) also must get ZERO citations!
        $resultPublic = $ragService->query(
            queryText: 'What is the stealth target valuation of Project Apex?',
            tenantId: null,
            userId: null
        );
        $this->assertCount(0, $resultPublic['source_citations']);
    }

    public function test_organization_member_inherits_pooled_subscription_entitlements(): void
    {
        $execPlan = Plan::where('slug', 'executive')->firstOrFail();

        $org = Organization::create([
            'name' => 'Enterprise Fund',
            'slug' => 'enterprise-fund',
            'max_seats' => 10,
            'is_active' => true,
        ]);

        // Active organization enterprise subscription
        Subscription::create([
            'organization_id' => $org->id,
            'plan_id' => $execPlan->id,
            'status' => 'active',
            'billing_cycle' => 'annual',
            'current_period_start' => now(),
            'current_period_end' => now()->addYear(),
        ]);

        $colleague = User::factory()->create(['account_type' => 'organization_member']);
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $colleague->id,
            'role' => 'member',
        ]);

        $entitlementService = app(EntitlementService::class);

        // Colleague has NO individual subscription, but inherits Executive PDF downloads & Deep Research
        $this->assertTrue($entitlementService->hasFeature($colleague, EntitlementService::FEATURE_PDF_DOWNLOAD));
        $this->assertTrue($entitlementService->hasFeature($colleague, EntitlementService::FEATURE_AI_DEEP_RESEARCH));
        $this->assertEquals(50, $entitlementService->getLimit($colleague, EntitlementService::FEATURE_MAX_WATCHLISTS));

        $snapshot = $entitlementService->getEntitlementsSnapshot($colleague);
        $this->assertTrue($snapshot['is_organization_pooled']);
        $this->assertEquals('Executive Enterprise', $snapshot['plan_name']);
        $this->assertEquals($org->name, $snapshot['organization']['name']);
    }

    public function test_admin_can_list_and_manage_organizations_and_seats(): void
    {
        $admin = User::factory()->create(['account_type' => 'chief_admin']);

        $org = Organization::create([
            'name' => 'Quantum Dynamics',
            'slug' => 'quantum-dynamics',
            'max_seats' => 5,
            'is_active' => true,
        ]);

        // 1. Admin Index
        $respIndex = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/organizations');
        $respIndex->assertStatus(200)
            ->assertJsonPath('success', true);

        // 2. Admin Telemetry Metrics
        $respMetrics = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/organizations/metrics');
        $respMetrics->assertStatus(200)
            ->assertJsonPath('metrics.total_organizations', 1)
            ->assertJsonPath('metrics.total_allocated_seats', 5);

        // 3. Admin adjusts seats
        $respSeats = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/admin/organizations/{$org->id}/seats", [
            'max_seats' => 25,
        ]);
        $respSeats->assertStatus(200);

        $org->refresh();
        $this->assertEquals(25, $org->max_seats);

        // 4. Admin toggles status to suspended
        $respStatus = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/admin/organizations/{$org->id}/status");
        $respStatus->assertStatus(200)
            ->assertJsonPath('is_active', false);

        $org->refresh();
        $this->assertFalse($org->is_active);
    }
}
