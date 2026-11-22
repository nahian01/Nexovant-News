<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\KnowledgeDocument;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\SemanticCache;
use App\Models\SubscriberWatchlist;
use App\Models\User;
use App\Services\Organization\OrganizationService;
use App\Services\RAG\KnowledgeBaseService;
use App\Services\Workspace\WatchlistService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Reset custom DNS resolver after tests
        OrganizationService::setDnsResolver(null);
        parent::tearDown();
    }

    public function test_login_rate_limiting_enforces_limit_and_returns_429(): void
    {
        // Send 6 invalid login attempts (the allowed threshold per minute)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'victim@nexovant.test',
                'password' => 'wrong-password',
            ]);
            $this->assertNotEquals(429, $response->status(), "Attempt {$i} should not be throttled yet.");
        }

        // The 7th attempt must be rejected with HTTP 429 Too Many Requests
        $throttledResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'victim@nexovant.test',
            'password' => 'wrong-password',
        ]);

        $throttledResponse->assertStatus(429);
    }

    public function test_admin_login_rate_limiting_enforces_limit_and_returns_429(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/v1/auth/admin-login', [
                'email' => 'admin-target@nexovant.test',
                'password' => 'wrong-admin-pass',
            ]);
            $this->assertNotEquals(429, $response->status(), "Admin attempt {$i} should not be throttled yet.");
        }

        $throttledResponse = $this->postJson('/api/v1/auth/admin-login', [
            'email' => 'admin-target@nexovant.test',
            'password' => 'wrong-admin-pass',
        ]);

        $throttledResponse->assertStatus(429);
    }

    public function test_domain_verification_rejects_already_claimed_verified_domain(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin']);
        $service = app(OrganizationService::class);

        $org1 = Organization::create([
            'name' => 'Original Corp',
            'slug' => 'original-corp',
            'owner_id' => $admin->id,
            'domain' => 'nexocorp.com',
            'domain_verified_at' => now(),
            'is_active' => true,
        ]);

        $org2 = Organization::create([
            'name' => 'Impersonator Corp',
            'slug' => 'impersonator-corp',
            'owner_id' => $admin->id,
            'domain' => 'nexocorp.com',
            'domain_verification_token' => 'nexo-verify-fake-token-123',
            'domain_verified_at' => null,
            'is_active' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("already verified by another organization");

        $service->confirmDomainVerification($org2, $admin);
    }

    public function test_domain_verification_fails_when_dns_txt_record_does_not_match(): void
    {
        $user = User::factory()->create();
        $service = app(OrganizationService::class);

        $org = Organization::create([
            'name' => 'Target Enterprise',
            'slug' => 'target-enterprise',
            'owner_id' => $user->id,
            'domain' => 'target-enterprise.com',
            'domain_verification_token' => 'nexo-verify-correct-token-abc',
            'domain_verified_at' => null,
            'is_active' => true,
        ]);

        // Mock DNS resolver returning a mismatched TXT record
        OrganizationService::setDnsResolver(function (string $host) {
            return [
                ['txt' => 'nexo-verify-wrong-token-xyz'],
            ];
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("DNS TXT verification failed");

        $service->confirmDomainVerification($org, $user);
        $this->assertNull($org->fresh()->domain_verified_at);
    }

    public function test_domain_verification_succeeds_when_dns_txt_record_matches(): void
    {
        $user = User::factory()->create();
        $service = app(OrganizationService::class);

        $expectedToken = 'nexo-verify-legit-secret-789';
        $org = Organization::create([
            'name' => 'Verified Enterprise',
            'slug' => 'verified-enterprise',
            'owner_id' => $user->id,
            'domain' => 'verified-enterprise.com',
            'domain_verification_token' => $expectedToken,
            'domain_verified_at' => null,
            'is_active' => true,
        ]);

        // Mock DNS resolver returning the expected token
        OrganizationService::setDnsResolver(function (string $host) use ($expectedToken) {
            return [
                ['txt' => $expectedToken],
            ];
        });

        $result = $service->confirmDomainVerification($org, $user);

        $this->assertTrue($result);
        $this->assertNotNull($org->fresh()->domain_verified_at);
        $this->assertDatabaseHas('organization_audit_logs', [
            'organization_id' => $org->id,
            'action' => 'domain.verified',
        ]);
    }

    public function test_organization_shared_watchlist_accessible_by_colleague_but_not_outsider(): void
    {
        $service = app(WatchlistService::class);

        $owner = User::factory()->create();
        $colleague = User::factory()->create(['account_type' => 'organization_member']);
        $outsider = User::factory()->create();

        $org = Organization::create([
            'name' => 'Shared Intelligence Group',
            'slug' => 'shared-intel',
            'owner_id' => $owner->id,
            'is_active' => true,
        ]);

        // Colleague belongs to the organization
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $colleague->id,
            'role' => OrganizationMember::ROLE_ANALYST,
        ]);

        // Create shared watchlist in organization
        $sharedWatchlist = SubscriberWatchlist::create([
            'user_id' => $owner->id,
            'organization_id' => $org->id,
            'name' => 'Semiconductor Supply Chain',
            'is_shared' => true,
            'is_default' => false,
        ]);

        // 1. Owner can access
        $ownerAccess = $service->getWatchlist($owner, $sharedWatchlist->id);
        $this->assertEquals($sharedWatchlist->id, $ownerAccess->id);

        // 2. Organization Colleague can access
        $colleagueAccess = $service->getWatchlist($colleague, $sharedWatchlist->id);
        $this->assertEquals($sharedWatchlist->id, $colleagueAccess->id);

        // 3. Outsider is rejected with 404 (IDOR protection)
        $this->expectException(ModelNotFoundException::class);
        $service->getWatchlist($outsider, $sharedWatchlist->id);
    }

    public function test_deleting_knowledge_document_invalidates_tenant_semantic_cache(): void
    {
        $kbService = app(KnowledgeBaseService::class);
        $org = Organization::create([
            'name' => 'Cache Test Enterprise',
            'slug' => 'cache-test-enterprise',
            'is_active' => true,
        ]);
        $tenantId = $org->id;

        // Create knowledge document for tenant
        $doc = KnowledgeDocument::create([
            'title' => 'Q4 Internal Earnings Analysis',
            'document_type' => 'financial_disclosure',
            'content_hash' => hash('sha256', 'Q4 Earnings disclosure content...'),
            'content_version' => hash('sha256', 'Q4 Earnings disclosure content...'),
            'chunk_count' => 1,
            'tenant_id' => $tenantId,
            'status' => 'indexed',
        ]);

        // Seed semantic cache entry for this tenant
        SemanticCache::create([
            'tenant_id' => $tenantId,
            'query_hash' => hash('sha256', 'what was Q4 revenue?'),
            'query_text' => 'what was Q4 revenue?',
            'response_text' => 'Q4 revenue was $4.2B.',
            'ttl_seconds' => 86400,
            'expires_at' => now()->addHours(24),
        ]);

        $this->assertDatabaseHas('semantic_cache', ['tenant_id' => $tenantId]);

        // Delete document via service
        $deleted = $kbService->deleteDocument($doc->id);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('knowledge_documents', ['id' => $doc->id]);

        // Semantic cache must be invalidated for this tenant
        $this->assertDatabaseMissing('semantic_cache', ['tenant_id' => $tenantId]);
    }

    public function test_stripe_webhook_rejects_missing_or_invalid_signature(): void
    {
        // Missing signature header
        $response = $this->postJson('/api/v1/webhooks/stripe', [
            'id' => 'evt_test_123',
            'type' => 'payment_intent.succeeded',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 400,
                'message' => 'Stripe webhook signature verification failed.',
            ]);

        // Invalid signature header
        $responseInvalid = $this->withHeaders([
            'Stripe-Signature' => 't=' . time() . ',v1=bad_hex_signature',
        ])->postJson('/api/v1/webhooks/stripe', [
            'id' => 'evt_test_123',
            'type' => 'payment_intent.succeeded',
        ]);

        $responseInvalid->assertStatus(400)
            ->assertJson([
                'status' => 400,
                'message' => 'Stripe webhook signature verification failed.',
            ]);
    }

    public function test_razorpay_webhook_rejects_missing_or_invalid_signature(): void
    {
        // Missing signature header
        $response = $this->postJson('/api/v1/webhooks/razorpay', [
            'event' => 'payment.captured',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 400,
                'message' => 'Razorpay webhook signature verification failed.',
            ]);

        // Invalid signature header
        $responseInvalid = $this->withHeaders([
            'X-Razorpay-Signature' => 'bad_signature_hash',
        ])->postJson('/api/v1/webhooks/razorpay', [
            'event' => 'payment.captured',
        ]);

        $responseInvalid->assertStatus(400)
            ->assertJson([
                'status' => 400,
                'message' => 'Razorpay webhook signature verification failed.',
            ]);
    }

    public function test_cannot_deactivate_or_demote_the_last_active_super_admin(): void
    {
        // Ensure only 1 active super admin exists
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin'],
            [
                'display_name' => 'Super Administrator',
                'description' => 'Platform Super Administrator',
            ]
        );

        $superAdmin = User::factory()->create([
            'email' => 'sole-superadmin@nexovant.test',
            'account_type' => 'admin',
            'is_active' => true,
        ]);
        $superAdmin->roles()->sync([$superAdminRole->id]);

        // 1. Attempt to deactivate the only Super Admin
        $deactivateResponse = $this->actingAs($superAdmin, 'sanctum')
            ->putJson("/api/v1/admin/users/{$superAdmin->id}/status");

        $deactivateResponse->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_SUSPEND_LAST_SUPER_ADMIN',
                ],
            ]);

        $this->assertTrue($superAdmin->fresh()->is_active);

        // 2. Attempt to demote the only Super Admin to subscriber
        $demoteResponse = $this->actingAs($superAdmin, 'sanctum')
            ->putJson("/api/v1/admin/users/{$superAdmin->id}/role", [
                'account_type' => 'individual',
            ]);

        $demoteResponse->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_DEMOTE_LAST_SUPER_ADMIN',
                ],
            ]);

        $this->assertEquals('admin', $superAdmin->fresh()->account_type);
    }

    public function test_trends_scan_endpoint_rate_limiting_enforces_limit_and_returns_429(): void
    {
        // Allowed threshold is 10 requests per minute
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/v1/trends/scan', ['time_window' => '24h']);
            $this->assertNotEquals(429, $response->status(), "Scan attempt {$i} should not be throttled yet.");
        }

        // The 11th request must be throttled with HTTP 429
        $throttled = $this->postJson('/api/v1/trends/scan', ['time_window' => '24h']);
        $throttled->assertStatus(429);
    }

    public function test_forgot_password_rate_limiting_enforces_limit_and_returns_429(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/v1/auth/forgot-password', [
                'email' => 'victim-recovery@nexovant.test',
            ]);
            $this->assertNotEquals(429, $response->status(), "Forgot password attempt {$i} should not be throttled.");
        }

        $throttled = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'victim-recovery@nexovant.test',
        ]);
        $throttled->assertStatus(429);
    }

    public function test_stripe_webhook_constant_time_hmac_verification(): void
    {
        Config::set('services.stripe.webhook_secret', 'whsec_test_secret_for_audit_32bytes');

        $payload = json_encode(['id' => 'evt_test_audit_1', 'type' => 'payment_intent.succeeded']);
        $time = time();
        $signedPayload = "{$time}.{$payload}";
        $validSignature = hash_hmac('sha256', $signedPayload, 'whsec_test_secret_for_audit_32bytes');

        // Valid signature passes
        $validResponse = $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => "t={$time},v1={$validSignature}",
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );
        $validResponse->assertStatus(200);

        // Tampered signature fails
        $invalidResponse = $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => "t={$time},v1=bad_signature_digest_12345",
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );
        $invalidResponse->assertStatus(400);
    }
}
