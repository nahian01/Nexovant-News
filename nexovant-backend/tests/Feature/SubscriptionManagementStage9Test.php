<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Plan;
use App\Models\PlanEntitlement;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionManagementStage9Test extends TestCase
{
    use RefreshDatabase;

    protected User $subscriber;
    protected User $otherUser;
    protected Plan $freePlan;
    protected Plan $proPlan;
    protected Plan $enterprisePlan;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        Role::firstOrCreate(['name' => 'subscriber'], ['display_name' => 'Subscriber', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin', 'guard_name' => 'web']);

        $this->subscriber = User::factory()->create([
            'email' => 'subscriber@nexovant.test',
            'name' => 'Executive Subscriber',
        ]);
        $this->subscriber->roles()->attach(Role::where('name', 'subscriber')->first());

        $this->otherUser = User::factory()->create([
            'email' => 'stranger@nexovant.test',
            'name' => 'Other Subscriber',
        ]);

        $this->freePlan = Plan::create([
            'id' => (string) Str::uuid(),
            'name' => 'Free Tier',
            'slug' => 'free',
            'description' => 'Essential access',
            'price_usd_monthly' => 0.00,
            'price_usd_annual' => 0.00,
            'price_bdt_monthly' => 0.00,
            'price_bdt_annual' => 0.00,
            'trial_period_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->proPlan = Plan::create([
            'id' => (string) Str::uuid(),
            'name' => 'Professional Intelligence',
            'slug' => 'pro',
            'description' => 'Comprehensive intelligence for analysts',
            'price_usd_monthly' => 79.00,
            'price_usd_annual' => 790.00,
            'price_bdt_monthly' => 8500.00,
            'price_bdt_annual' => 85000.00,
            'trial_period_days' => 14,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->enterprisePlan = Plan::create([
            'id' => (string) Str::uuid(),
            'name' => 'Enterprise Platform',
            'slug' => 'enterprise',
            'description' => 'Full organizational multi-seat access',
            'price_usd_monthly' => 299.00,
            'price_usd_annual' => 2990.00,
            'price_bdt_monthly' => 32000.00,
            'price_bdt_annual' => 320000.00,
            'trial_period_days' => 30,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // Entitlements
        PlanEntitlement::create([
            'id' => (string) Str::uuid(),
            'plan_id' => $this->proPlan->id,
            'feature_key' => EntitlementService::FEATURE_MAX_WATCHLISTS,
            'value' => '15',
        ]);
        PlanEntitlement::create([
            'id' => (string) Str::uuid(),
            'plan_id' => $this->proPlan->id,
            'feature_key' => EntitlementService::FEATURE_MAX_ACTIVE_MONITORS,
            'value' => '25',
        ]);
        PlanEntitlement::create([
            'id' => (string) Str::uuid(),
            'plan_id' => $this->proPlan->id,
            'feature_key' => EntitlementService::FEATURE_DAILY_RESEARCH_QUERIES,
            'value' => '50',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_billing_overview(): void
    {
        $response = $this->getJson('/api/v1/billing/overview');
        $response->assertStatus(401);
    }

    public function test_subscriber_overview_returns_subscription_usage_and_entitlements(): void
    {
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriber->id,
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'status' => 'active',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
            'next_renewal_at' => now()->addDays(25),
        ]);

        $response = $this->actingAs($this->subscriber)->getJson('/api/v1/billing/overview');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $this->subscriber->id)
            ->assertJsonPath('data.subscription.id', $sub->id)
            ->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.subscription.is_active', true)
            ->assertJsonPath('data.subscription.is_trialing', false)
            ->assertJsonPath('data.subscription.plan.name', 'Professional Intelligence')
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'subscription',
                    'entitlements',
                    'usage' => ['watchlists', 'monitors', 'daily_queries'],
                    'eligible_payment_methods',
                ],
            ]);
    }

    public function test_start_trial_activates_complimentary_trial(): void
    {
        $response = $this->actingAs($this->subscriber)->postJson('/api/v1/billing/start-trial', [
            'plan_id' => $this->proPlan->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'trialing');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->subscriber->id,
            'plan_id' => $this->proPlan->id,
            'status' => 'trialing',
        ]);
    }

    public function test_start_trial_prevents_duplicate_trial_or_re_activation(): void
    {
        // First trial starts cleanly
        $this->actingAs($this->subscriber)->postJson('/api/v1/billing/start-trial', [
            'plan_id' => $this->proPlan->id,
        ])->assertStatus(201);

        // Second trial attempt is blocked
        $secondResponse = $this->actingAs($this->subscriber)->postJson('/api/v1/billing/start-trial', [
            'plan_id' => $this->enterprisePlan->id,
        ]);

        $secondResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Free trial is only available once per account, or you already have an active subscription.');
    }

    public function test_plans_listing_returns_active_plans_with_dual_pricing(): void
    {
        $response = $this->getJson('/api/v1/billing/plans');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $plans = $response->json('data');
        $this->assertGreaterThanOrEqual(3, count($plans));

        $pro = collect($plans)->firstWhere('slug', 'pro');
        $this->assertEquals(79.00, (float) $pro['price_usd_monthly']);
        $this->assertEquals(8500.00, (float) $pro['price_bdt_monthly']);
    }

    public function test_payment_methods_filters_by_region_and_currency(): void
    {
        // Bangladesh + BDT
        $resBdt = $this->actingAs($this->subscriber)->getJson('/api/v1/billing/payment-methods?country=BD&currency=BDT');
        $resBdt->assertOk();
        $methodsBdt = collect($resBdt->json('data'))->pluck('id')->toArray();
        $this->assertContains('bkash', $methodsBdt);
        $this->assertContains('manual_bank', $methodsBdt);

        // US + USD
        $resUsd = $this->actingAs($this->subscriber)->getJson('/api/v1/billing/payment-methods?country=US&currency=USD');
        $resUsd->assertOk();
        $methodsUsd = collect($resUsd->json('data'))->pluck('id')->toArray();
        $this->assertContains('stripe_card', $methodsUsd);
        $this->assertContains('manual_bank', $methodsUsd);
    }

    public function test_coupon_validation_and_discount_calculation(): void
    {
        $coupon = Coupon::create([
            'id' => (string) Str::uuid(),
            'name' => '25% Executive Discount',
            'code' => 'NEXO25',
            'discount_type' => 'percentage',
            'discount_value' => 25.00,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($this->subscriber)->postJson('/api/v1/billing/validate-coupon', [
            'code' => 'NEXO25',
            'plan_id' => $this->proPlan->id,
            'subtotal' => 100.00,
            'country' => 'US',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'NEXO25')
            ->assertJsonPath('data.discount_amount', 25)
            ->assertJsonPath('data.final_amount', 75);
    }

    public function test_checkout_recalculates_price_server_side_and_creates_invoice(): void
    {
        $response = $this->actingAs($this->subscriber)->postJson('/api/v1/billing/checkout', [
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'country' => 'US',
            'payment_method' => 'stripe',
            'idempotency_key' => 'idemp_' . Str::random(12),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('invoices', [
            'currency' => 'USD',
            'total' => 79.00, // Exact server-side calculated price
        ]);
    }

    public function test_checkout_deduplicates_by_idempotency_key(): void
    {
        $key = 'idemp_unique_stage9_test';

        $res1 = $this->actingAs($this->subscriber)->postJson('/api/v1/billing/checkout', [
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'country' => 'US',
            'payment_method' => 'stripe',
            'idempotency_key' => $key,
        ]);
        $res1->assertStatus(201);

        $res2 = $this->actingAs($this->subscriber)->postJson('/api/v1/billing/checkout', [
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'country' => 'US',
            'payment_method' => 'stripe',
            'idempotency_key' => $key,
        ]);

        $res2->assertStatus(201)
            ->assertJsonPath('data.is_duplicate', true);
    }

    public function test_proration_preview_returns_calculation_without_modifying_database(): void
    {
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriber->id,
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'status' => 'active',
            'starts_at' => now()->subDays(15),
            'ends_at' => now()->addDays(15),
            'next_renewal_at' => now()->addDays(15),
        ]);

        $response = $this->actingAs($this->subscriber)->postJson('/api/v1/billing/preview-proration', [
            'target_plan_id' => $this->enterprisePlan->id,
            'new_interval' => 'monthly',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'current_plan',
                    'target_plan',
                    'unused_credit',
                    'target_price',
                    'amount_due',
                ],
            ]);

        // Subscription remains on pro plan
        $this->assertEquals($this->proPlan->id, $sub->fresh()->plan_id);
    }

    public function test_change_plan_executes_upgrade_and_transitions_tier(): void
    {
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriber->id,
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'status' => 'active',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20),
            'next_renewal_at' => now()->addDays(20),
        ]);

        $response = $this->actingAs($this->subscriber)->postJson('/api/v1/billing/change-plan', [
            'target_plan_id' => $this->enterprisePlan->id,
            'new_interval' => 'monthly',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals($this->enterprisePlan->id, $sub->fresh()->plan_id);
    }

    public function test_cancel_subscription_at_period_end_maintains_access(): void
    {
        $endsAt = now()->addDays(20);
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriber->id,
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'status' => 'active',
            'starts_at' => now()->subDays(10),
            'ends_at' => $endsAt,
            'next_renewal_at' => $endsAt,
        ]);

        $response = $this->actingAs($this->subscriber)->postJson('/api/v1/billing/cancel', [
            'at_period_end' => true,
            'reason' => 'Downsizing budget',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'cancel_at_period_end');

        $this->assertEquals('cancel_at_period_end', $sub->fresh()->status);
        $this->assertTrue($sub->fresh()->isActive());
    }

    public function test_reactivate_subscription_restores_active_auto_renewal(): void
    {
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriber->id,
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'status' => 'cancel_at_period_end',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20),
        ]);

        $response = $this->actingAs($this->subscriber)->postJson('/api/v1/billing/reactivate');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active');

        $this->assertEquals('active', $sub->fresh()->status);
    }

    public function test_cross_user_invoice_access_blocked_with_403_forbidden(): void
    {
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriber->id,
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'id' => (string) Str::uuid(),
            'subscription_id' => $sub->id,
            'invoice_number' => 'INV-SEC-403',
            'subtotal' => 79.00,
            'total' => 79.00,
            'currency' => 'USD',
            'status' => 'paid',
            'due_date' => now()->addDays(7),
        ]);

        // Attempt download as other user
        $response = $this->actingAs($this->otherUser)->getJson("/api/v1/billing/invoices/{$invoice->id}/download");

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthorized access to invoice.');
    }

    public function test_submit_manual_payment_proof_creates_pending_review_and_deduplicates(): void
    {
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriber->id,
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'BDT',
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'id' => (string) Str::uuid(),
            'subscription_id' => $sub->id,
            'invoice_number' => 'INV-MFS-101',
            'subtotal' => 8500.00,
            'total' => 8500.00,
            'currency' => 'BDT',
            'status' => 'unpaid',
            'due_date' => now()->addDays(7),
        ]);

        $res1 = $this->actingAs($this->subscriber)->postJson('/api/v1/billing/submit-manual-payment', [
            'invoice_id' => $invoice->id,
            'payment_method' => 'manual_bkash',
            'transaction_id' => 'TRX_BKASH_999888',
            'sender_account' => '01711000000',
            'notes' => 'Executive monthly renewal',
        ]);

        $res1->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending_review');

        // Duplicate submission of same TrxID fails
        $res2 = $this->actingAs($this->subscriber)->postJson('/api/v1/billing/submit-manual-payment', [
            'invoice_id' => $invoice->id,
            'payment_method' => 'manual_bkash',
            'transaction_id' => 'TRX_BKASH_999888',
        ]);

        $res2->assertStatus(422)
            ->assertJsonPath('error.code', 'DUPLICATE_TRANSACTION');
    }

    public function test_organization_billing_visibility_reflects_pooled_entitlements(): void
    {
        $org = Organization::create([
            'id' => (string) Str::uuid(),
            'name' => 'Acme Quantum Systems',
            'slug' => 'acme-quantum',
            'is_active' => true,
        ]);

        OrganizationMember::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $org->id,
            'user_id' => $this->subscriber->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        Subscription::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $org->id,
            'plan_id' => $this->enterprisePlan->id,
            'billing_cycle' => 'annual',
            'currency' => 'USD',
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonths(11),
        ]);

        $response = $this->actingAs($this->subscriber)->getJson('/api/v1/billing/overview');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.organization.id', $org->id)
            ->assertJsonPath('data.organization.name', 'Acme Quantum Systems')
            ->assertJsonPath('data.organization.role', 'owner')
            ->assertJsonPath('data.organization.is_owner', true)
            ->assertJsonPath('data.organization.is_pooled', true)
            ->assertJsonPath('data.organization.subscription.plan_name', 'Enterprise Platform');
    }
}
