<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionEntitlementOverride;
use App\Models\User;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\GatewayRegistryService;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\PaymentReconciliationService;
use App\Services\Billing\RegionalPaymentSelectionService;
use App\Services\Billing\SubscriptionLifecycleService;
use App\Services\Billing\SubscriptionProrationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $subscriberA;
    protected User $subscriberB;
    protected Plan $freePlan;
    protected Plan $proPlan;
    protected Plan $executivePlan;
    protected Plan $enterprisePlan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->adminUser = User::where('email', 'admin@nexovant.si')->firstOrFail();

        $subscriberRole = Role::where('name', 'subscriber')->first();

        $this->subscriberA = User::factory()->create([
            'name' => 'Alice Walker',
            'email' => 'alice@enterprise.com',
            'account_type' => 'individual',
        ]);
        if ($subscriberRole) {
            $this->subscriberA->roles()->attach($subscriberRole->id);
        }

        $this->subscriberB = User::factory()->create([
            'name' => 'Bob Davis',
            'email' => 'bob@corp.com',
            'account_type' => 'individual',
        ]);
        if ($subscriberRole) {
            $this->subscriberB->roles()->attach($subscriberRole->id);
        }

        $this->freePlan = Plan::firstOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free Tier',
                'price_usd_monthly' => 0.00,
                'price_usd_annual' => 0.00,
                'price_bdt_monthly' => 0.00,
                'price_bdt_annual' => 0.00,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $this->proPlan = Plan::firstOrCreate(
            ['slug' => 'professional'],
            [
                'name' => 'Professional Intelligence',
                'price_usd_monthly' => 49.00,
                'price_usd_annual' => 470.00,
                'price_bdt_monthly' => 5500.00,
                'price_bdt_annual' => 52000.00,
                'trial_period_days' => 14,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        $this->executivePlan = Plan::firstOrCreate(
            ['slug' => 'executive'],
            [
                'name' => 'Executive Suite',
                'price_usd_monthly' => 149.00,
                'price_usd_annual' => 1430.00,
                'price_bdt_monthly' => 16500.00,
                'price_bdt_annual' => 158000.00,
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        $this->enterprisePlan = Plan::firstOrCreate(
            ['slug' => 'enterprise-test'],
            [
                'name' => 'Enterprise Intelligence Test',
                'price_usd_monthly' => 199.00,
                'price_usd_annual' => 1900.00,
                'price_bdt_monthly' => 22000.00,
                'price_bdt_annual' => 210000.00,
                'is_active' => true,
                'sort_order' => 10,
            ]
        );
    }

    /**
     * Test 1: Active plans listing returns dual pricing and entitlements.
     */
    public function test_plans_listing_returns_active_plans_with_dual_pricing(): void
    {
        $response = $this->getJson('/api/v1/billing/plans');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'slug',
                        'price_usd_monthly', 'price_usd_annual',
                        'price_bdt_monthly', 'price_bdt_annual',
                        'entitlements',
                    ]
                ]
            ]);
    }

    /**
     * Test 2: Regional payment method selection filters appropriately.
     */
    public function test_regional_payment_method_selection_filters_by_country_and_currency(): void
    {
        // Bangladesh + BDT
        $resBd = $this->getJson('/api/v1/billing/payment-methods?country=BD&currency=BDT');
        $resBd->assertStatus(200);
        $methodsBd = collect($resBd->json('data'))->pluck('id')->toArray();
        $this->assertContains('bkash', $methodsBd);
        $this->assertContains('nagad', $methodsBd);
        $this->assertContains('manual_bank', $methodsBd);

        // US + USD
        $resUs = $this->getJson('/api/v1/billing/payment-methods?country=US&currency=USD');
        $resUs->assertStatus(200);
        $methodsUs = collect($resUs->json('data'))->pluck('id')->toArray();
        $this->assertContains('stripe_card', $methodsUs);
        $this->assertContains('manual_bank', $methodsUs);
        $this->assertNotContains('bkash', $methodsUs);
    }

    /**
     * Test 3: Entitlement engine evaluates limits and administrative overrides.
     */
    public function test_entitlement_service_evaluates_limits_and_overrides(): void
    {
        $entitlementService = app(EntitlementService::class);

        // Default limit for Free plan
        $limit = $entitlementService->getLimit($this->subscriberA, 'max_watchlists', 3);
        $this->assertEquals(3, $limit);

        // Grant temporary admin override for 25 watchlists
        SubscriptionEntitlementOverride::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriberA->id,
            'feature_key' => 'max_watchlists',
            'value' => '25',
            'granted_by_user_id' => $this->adminUser->id,
            'reason' => 'VIP pilot program',
            'expires_at' => now()->addDays(7),
        ]);

        $overriddenLimit = $entitlementService->getLimit($this->subscriberA, 'max_watchlists', 3);
        $this->assertEquals(25, $overriddenLimit);

        // Subscriber B without override has standard limit
        $limitB = $entitlementService->getLimit($this->subscriberB, 'max_watchlists', 3);
        $this->assertEquals(3, $limitB);
    }

    /**
     * Test 4: Checkout workflow with backend pricing recalculation (client price manipulation ignored).
     */
    public function test_checkout_recalculates_price_on_backend_and_creates_invoice(): void
    {
        $this->actingAs($this->subscriberA);

        $response = $this->postJson('/api/v1/billing/checkout', [
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'country' => 'US',
            'payment_method' => 'manual_bank',
            'idempotency_key' => 'test_idemp_' . Str::random(10),
            // Malicious client attempt to set price to $1.00
            'price' => 1.00,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $invoice = Invoice::findOrFail($response->json('data.invoice.id'));
        // Backend must enforce real plan price from database ($9.00) ignoring client request ($1.00)
        $this->assertEquals((float) $this->proPlan->price_usd_monthly, (float) $invoice->total);
        $this->assertEquals('unpaid', $invoice->status);
    }

    /**
     * Test 5: Idempotency key prevents duplicate charge and payment creation.
     */
    public function test_checkout_enforces_idempotency_key_deduplication(): void
    {
        $this->actingAs($this->subscriberA);
        $idempKey = 'idemp_key_unique_test_' . Str::random(8);

        $payload = [
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'country' => 'US',
            'payment_method' => 'manual_bank',
            'idempotency_key' => $idempKey,
        ];

        $res1 = $this->postJson('/api/v1/billing/checkout', $payload);
        $res1->assertStatus(201);

        // Second submission with identical key
        $res2 = $this->postJson('/api/v1/billing/checkout', $payload);
        $res2->assertStatus(201)
            ->assertJson(['data' => ['is_duplicate' => true]]);
    }

    /**
     * Test 6: Promotional coupon calculates discount and prevents double redemption.
     */
    public function test_coupon_validates_and_enforces_single_redemption_per_user(): void
    {
        $coupon = Coupon::create([
            'id' => (string) Str::uuid(),
            'code' => 'LAUNCH20',
            'name' => '20% Launch Discount',
            'discount_type' => 'percentage',
            'discount_value' => 20.00,
            'is_active' => true,
        ]);

        $this->actingAs($this->subscriberA);

        // Validate coupon
        $resVal = $this->postJson('/api/v1/billing/validate-coupon', [
            'code' => 'LAUNCH20',
            'plan_id' => $this->proPlan->id,
            'country' => 'US',
            'subtotal' => 49.00,
        ]);

        $resVal->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'discount_amount' => 9.80,
                    'final_amount' => 39.20,
                ]
            ]);

        // Checkout with coupon
        $resCheckout = $this->postJson('/api/v1/billing/checkout', [
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'country' => 'US',
            'payment_method' => 'manual_bank',
            'coupon_code' => 'LAUNCH20',
        ]);
        $resCheckout->assertStatus(201);

        // Attempting to reuse the same coupon must be rejected
        $resReuse = $this->postJson('/api/v1/billing/validate-coupon', [
            'code' => 'LAUNCH20',
            'plan_id' => $this->proPlan->id,
            'country' => 'US',
            'subtotal' => 49.00,
        ]);
        $resReuse->assertStatus(422);
    }

    /**
     * Test 7: Subscription lifecycle state machine (active -> cancel_at_period_end -> reactivate -> cancel).
     */
    public function test_subscription_lifecycle_state_machine_transitions(): void
    {
        $lifecycleService = app(SubscriptionLifecycleService::class);

        // Create initial active subscription
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriberA->id,
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'status' => 'pending_payment',
        ]);

        // 1. Activate
        $lifecycleService->activateSubscription($sub, null, $this->adminUser, 'Admin verified payment');
        $sub->refresh();
        $this->assertEquals('active', $sub->status);
        $this->assertTrue($sub->isActive());

        // 2. Cancel at period end
        $lifecycleService->cancelSubscription($sub, $this->subscriberA, true);
        $sub->refresh();
        $this->assertEquals('cancel_at_period_end', $sub->status);
        $this->assertTrue($sub->isActive()); // Remains active until ends_at

        // 3. Reactivate before period ends
        $lifecycleService->reactivateSubscription($sub, $this->subscriberA);
        $sub->refresh();
        $this->assertEquals('active', $sub->status);

        // 4. Immediate cancellation
        $lifecycleService->cancelSubscription($sub, $this->subscriberA, false);
        $sub->refresh();
        $this->assertEquals('canceled', $sub->status);
        $this->assertFalse($sub->isActive());
    }

    /**
     * Test 8: Proration engine calculates unused cycle credit on upgrade.
     */
    public function test_proration_engine_credits_unused_time_towards_upgrade(): void
    {
        $prorationService = app(SubscriptionProrationService::class);

        // Active monthly plan ($49.00) with 15 days remaining out of 30
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriberA->id,
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'status' => 'active',
            'starts_at' => now()->subDays(15),
            'ends_at' => now()->addDays(15),
        ]);

        $proration = $prorationService->calculateProration($sub, $this->enterprisePlan, 'monthly');

        $this->assertEquals('upgrade', $proration['type']);
        $this->assertEquals(199.00, $proration['target_price']);
        // Unused credit should be approximately half of $9 = $4.50
        $this->assertGreaterThan(4.0, $proration['unused_credit']);
        $this->assertLessThan(5.0, $proration['unused_credit']);
        // Amount due = $199 - unused credit
        $this->assertLessThan(199.00, $proration['amount_due']);
        $this->assertGreaterThan(194.00, $proration['amount_due']);
    }

    /**
     * Test 9: Invoice receipt PDF generation and cross-user tenant isolation.
     */
    public function test_invoice_receipt_pdf_generation_and_access_security(): void
    {
        $invoiceService = app(InvoiceService::class);

        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriberA->id,
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $invoice = $invoiceService->createInvoice(
            $sub,
            49.00,
            0.0,
            0.0,
            null,
            ['name' => 'Alice Walker', 'email' => 'alice@enterprise.com']
        );

        // Generate PDF
        $pdfPath = $invoiceService->generatePdf($invoice);
        $this->assertNotEmpty($pdfPath);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($pdfPath));

        // User A (owner) can download
        $this->actingAs($this->subscriberA);
        $resOwner = $this->get("/api/v1/billing/invoices/{$invoice->id}/download");
        $resOwner->assertStatus(200);

        // User B (unauthorized) cannot download User A's invoice
        $this->actingAs($this->subscriberB);
        $resOther = $this->getJson("/api/v1/billing/invoices/{$invoice->id}/download");
        $resOther->assertStatus(403);
    }

    /**
     * Test 10: Refund processing and safety limits.
     */
    public function test_admin_refund_processing_and_validation(): void
    {
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriberA->id,
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'id' => (string) Str::uuid(),
            'subscription_id' => $sub->id,
            'invoice_number' => 'INV-TEST-999',
            'subtotal' => 49.00,
            'discount' => 0.00,
            'tax' => 0.00,
            'total' => 49.00,
            'currency' => 'USD',
            'status' => 'paid',
            'due_date' => now()->addDays(7),
        ]);

        $payment = Payment::create([
            'id' => (string) Str::uuid(),
            'invoice_id' => $invoice->id,
            'gateway_key' => 'manual_bank',
            'payment_method' => 'manual_bank',
            'provider_reference' => 'WIRE-REF-100',
            'amount' => 49.00,
            'currency' => 'USD',
            'status' => 'verified',
        ]);

        $this->actingAs($this->adminUser);

        // Process partial refund of $20.00
        $resRefund = $this->postJson('/api/v1/admin/billing/refunds', [
            'payment_id' => $payment->id,
            'amount' => 20.00,
            'reason' => 'Customer service courtesy adjustment',
        ]);

        $resRefund->assertStatus(201)
            ->assertJson(['success' => true]);

        // Attempting to refund $35.00 (which exceeds the remaining $29.00) must fail
        $resOverRefund = $this->postJson('/api/v1/admin/billing/refunds', [
            'payment_id' => $payment->id,
            'amount' => 35.00,
            'reason' => 'Excessive refund attempt',
        ]);

        $resOverRefund->assertStatus(422);
    }

    /**
     * Test 11: Gateway capability registry reports unconfigured status truthfully.
     */
    public function test_gateway_registry_reports_credentials_and_status_truthfully(): void
    {
        $this->actingAs($this->adminUser);

        $res = $this->getJson('/api/v1/admin/billing/gateways');
        $res->assertStatus(200)
            ->assertJson(['success' => true]);

        $gateways = collect($res->json('data'))->keyBy('gateway_key');

        // Check Stripe and Razorpay
        $this->assertArrayHasKey('stripe', $gateways);
        $this->assertArrayHasKey('manual_bank', $gateways);
        $this->assertArrayHasKey('bkash', $gateways);

        // Manual bank should be configured by default
        $this->assertTrue($gateways['manual_bank']['is_configured']);
    }

    /**
     * Test 12: Financial reconciliation run audits transactions and logs discrepancies.
     */
    public function test_payment_reconciliation_audits_anomalies_and_stores_run(): void
    {
        $this->actingAs($this->adminUser);

        // Create an unbacked paid invoice (paid without verified payment)
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriberB->id,
            'plan_id' => $this->proPlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'status' => 'active',
        ]);

        Invoice::create([
            'id' => (string) Str::uuid(),
            'subscription_id' => $sub->id,
            'invoice_number' => 'INV-UNBACKED-001',
            'subtotal' => 100.00,
            'discount' => 0.00,
            'tax' => 0.00,
            'total' => 100.00,
            'currency' => 'USD',
            'status' => 'paid',
            'due_date' => now()->addDays(7),
        ]);

        $res = $this->postJson('/api/v1/admin/billing/reconcile');

        $res->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'run_id', 'discrepancies_found', 'discrepancies'
                ]
            ]);

        $this->assertGreaterThanOrEqual(1, $res->json('data.discrepancies_found'));
    }

    /**
     * Test 13: Subscriber billing overview returns complete telemetry and usage.
     */
    public function test_subscriber_overview_returns_entitlements_and_live_usage(): void
    {
        $this->actingAs($this->subscriberA);

        $res = $this->getJson('/api/v1/billing/overview');

        $res->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'entitlements' => ['limits', 'features'],
                    'usage' => ['watchlists', 'monitors', 'daily_queries'],
                    'eligible_payment_methods',
                ]
            ]);
    }
}
