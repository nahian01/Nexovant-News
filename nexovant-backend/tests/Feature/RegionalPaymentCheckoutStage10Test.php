<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\PaymentProof;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\GatewayRegistryService;
use App\Services\Billing\PaymentReconciliationService;
use App\Services\Billing\RegionalPaymentSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegionalPaymentCheckoutStage10Test extends TestCase
{
    use RefreshDatabase;

    protected User $subscriber;
    protected User $otherSubscriber;
    protected User $admin;
    protected Plan $executivePlan;
    protected Plan $professionalPlan;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed gateway registry
        app(GatewayRegistryService::class)->ensureGatewaysSeeded();

        // Seed standard test plans
        $this->executivePlan = Plan::create([
            'id' => (string) Str::uuid(),
            'name' => 'Executive Intelligence',
            'slug' => 'executive',
            'tier' => 'executive',
            'description' => 'Comprehensive executive intelligence and deep research.',
            'price_usd_monthly' => 199.00,
            'price_usd_annual' => 1990.00,
            'price_bdt_monthly' => 22000.00,
            'price_bdt_annual' => 220000.00,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->professionalPlan = Plan::create([
            'id' => (string) Str::uuid(),
            'name' => 'Professional Analyst',
            'slug' => 'professional',
            'tier' => 'professional',
            'description' => 'Standard technology intelligence access.',
            'price_usd_monthly' => 49.00,
            'price_usd_annual' => 490.00,
            'price_bdt_monthly' => 5500.00,
            'price_bdt_annual' => 55000.00,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->subscriber = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Executive Subscriber',
            'email' => 'subscriber@nexovant.test',
            'password' => bcrypt('Password123!'),
            'account_type' => 'subscriber',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->otherSubscriber = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Other Executive',
            'email' => 'other@nexovant.test',
            'password' => bcrypt('Password123!'),
            'account_type' => 'subscriber',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Platform Administrator',
            'email' => 'admin@nexovant.si',
            'password' => bcrypt('Password123!'),
            'account_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Test 1: Bangladesh region correctly returns bKash, Nagad, and Manual Bank.
     */
    public function test_bangladesh_region_returns_bkash_nagad_and_manual_bank(): void
    {
        $service = app(RegionalPaymentSelectionService::class);
        $methods = $service->getEligiblePaymentMethods('BD', 'BDT', 'monthly');

        $keys = array_column($methods, 'gateway_key');

        $this->assertContains('bkash', $keys);
        $this->assertContains('nagad', $keys);
        $this->assertContains('manual_bank', $keys);
    }

    /**
     * Test 2: Global/US region returns Stripe and Manual Bank (no bKash/Nagad).
     */
    public function test_international_region_returns_stripe_and_manual_bank(): void
    {
        $service = app(RegionalPaymentSelectionService::class);
        $methods = $service->getEligiblePaymentMethods('US', 'USD', 'monthly');

        $keys = array_column($methods, 'gateway_key');

        $this->assertContains('stripe', $keys);
        $this->assertContains('manual_bank', $keys);
        $this->assertNotContains('bkash', $keys);
        $this->assertNotContains('nagad', $keys);
    }

    /**
     * Test 3: Currency validation strictly rejects BDT outside Bangladesh.
     */
    public function test_currency_validation_rejects_bdt_for_non_bangladesh_countries(): void
    {
        $service = app(RegionalPaymentSelectionService::class);

        $this->assertTrue($service->validateCountryCurrency('BD', 'BDT'));
        $this->assertFalse($service->validateCountryCurrency('US', 'BDT'));
        $this->assertFalse($service->validateCountryCurrency('GB', 'BDT'));
        $this->assertTrue($service->validateCountryCurrency('US', 'USD'));
        $this->assertTrue($service->validateCountryCurrency('GB', 'GBP'));
        $this->assertTrue($service->validateCountryCurrency('DE', 'EUR'));
    }

    /**
     * Test 4: Checkout options endpoint returns structured countries, currencies, and methods.
     */
    public function test_checkout_options_endpoint_returns_countries_and_currencies(): void
    {
        $response = $this->actingAs($this->subscriber)
            ->getJson('/api/v1/billing/checkout-options?country=BD&currency=BDT');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('country', 'BD')
            ->assertJsonPath('currency', 'BDT')
            ->assertJsonStructure([
                'success',
                'country',
                'currency',
                'countries',
                'currencies',
                'eligible_payment_methods'
            ]);

        $methods = $response->json('eligible_payment_methods');
        $keys = array_column($methods, 'gateway_key');
        $this->assertContains('bkash', $keys);
        $this->assertContains('nagad', $keys);
    }

    /**
     * Test 5: Checkout initiation with bKash creates pending payment and instructions.
     */
    public function test_checkout_with_bkash_creates_pending_payment_and_instructions(): void
    {
        $response = $this->actingAs($this->subscriber)
            ->postJson('/api/v1/billing/checkout', [
                'plan_id' => $this->executivePlan->id,
                'billing_cycle' => 'monthly',
                'currency' => 'BDT',
                'country' => 'BD',
                'payment_method' => 'bkash',
                'billing_name' => 'Ashfaq Ahmed',
                'billing_email' => 'subscriber@nexovant.test',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.gateway_key', 'bkash')
            ->assertJsonPath('data.payment.currency', 'BDT')
            ->assertJsonPath('data.payment.amount', '22000.00');

        $this->assertDatabaseHas('invoices', [
            'total' => '22000.00',
            'currency' => 'BDT',
            'status' => 'unpaid',
        ]);

        $this->assertDatabaseHas('payments', [
            'payment_method' => 'bkash',
            'currency' => 'BDT',
            'status' => 'pending',
        ]);
    }

    /**
     * Test 6: Checkout initiation with Nagad creates pending payment and instructions.
     */
    public function test_checkout_with_nagad_creates_pending_payment_and_instructions(): void
    {
        $response = $this->actingAs($this->subscriber)
            ->postJson('/api/v1/billing/checkout', [
                'plan_id' => $this->executivePlan->id,
                'billing_cycle' => 'annual',
                'currency' => 'BDT',
                'country' => 'BD',
                'payment_method' => 'nagad',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.gateway_key', 'nagad')
            ->assertJsonPath('data.payment.amount', '220000.00');

        $this->assertDatabaseHas('payments', [
            'payment_method' => 'nagad',
            'currency' => 'BDT',
            'status' => 'pending',
        ]);
    }

    /**
     * Test 7: Checkout initiation with Stripe creates transaction reference.
     */
    public function test_checkout_with_stripe_creates_transaction_reference(): void
    {
        // Ensure Stripe is enabled in test mode
        PaymentGateway::where('gateway_key', 'stripe')->update([
            'is_configured' => true,
            'is_active' => true,
            'merchant_verified' => true,
        ]);

        $response = $this->actingAs($this->subscriber)
            ->postJson('/api/v1/billing/checkout', [
                'plan_id' => $this->executivePlan->id,
                'billing_cycle' => 'monthly',
                'currency' => 'USD',
                'country' => 'US',
                'payment_method' => 'stripe',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.gateway_key', 'stripe')
            ->assertJsonPath('data.payment.currency', 'USD')
            ->assertJsonPath('data.payment.amount', '199.00');

        $this->assertDatabaseHas('payments', [
            'gateway_key' => 'stripe',
            'currency' => 'USD',
            'status' => 'pending',
        ]);
    }

    /**
     * Test 8: Checkout initiation with Manual Bank returns City Bank / SCB instructions.
     */
    public function test_checkout_with_manual_bank_returns_corporate_account_instructions(): void
    {
        $response = $this->actingAs($this->subscriber)
            ->postJson('/api/v1/billing/checkout', [
                'plan_id' => $this->executivePlan->id,
                'billing_cycle' => 'annual',
                'currency' => 'BDT',
                'country' => 'BD',
                'payment_method' => 'manual_bank',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.gateway_key', 'manual_bank')
            ->assertJsonPath('data.instructions.bank_name', 'City Bank PLC');

        $this->assertDatabaseHas('payments', [
            'payment_method' => 'manual_bank',
            'status' => 'pending_review',
        ]);
    }

    /**
     * Test 9: Idempotency key prevents duplicate checkout payment records.
     */
    public function test_checkout_idempotency_prevents_duplicate_payments(): void
    {
        $idempKey = 'idemp_test_stage10_' . Str::random(10);

        $payload = [
            'plan_id' => $this->executivePlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'BDT',
            'country' => 'BD',
            'payment_method' => 'bkash',
            'idempotency_key' => $idempKey,
        ];

        $res1 = $this->actingAs($this->subscriber)->postJson('/api/v1/billing/checkout', $payload);
        $res1->assertStatus(201);

        $res2 = $this->actingAs($this->subscriber)->postJson('/api/v1/billing/checkout', $payload);
        $res2->assertStatus(201)
            ->assertJsonPath('data.is_duplicate', true);

        $this->assertEquals(1, Payment::where('idempotency_key', $idempKey)->count());
    }

    /**
     * Test 10: Coupon application correctly reduces total due on invoice.
     */
    public function test_checkout_coupon_application_reduces_total_due(): void
    {
        $coupon = Coupon::create([
            'id' => (string) Str::uuid(),
            'code' => 'STAGE10TEST',
            'name' => 'Stage 10 Test Discount',
            'discount_type' => 'percentage',
            'discount_value' => 20.00,
            'is_active' => true,
            'max_uses' => 100,
            'times_used' => 0,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $response = $this->actingAs($this->subscriber)
            ->postJson('/api/v1/billing/checkout', [
                'plan_id' => $this->executivePlan->id,
                'billing_cycle' => 'monthly',
                'currency' => 'USD',
                'country' => 'US',
                'payment_method' => 'manual_bank',
                'coupon_code' => 'STAGE10TEST',
            ]);

        // Base 199.00 - 20% (39.80) = 159.20
        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('invoices', [
            'subtotal' => '199.00',
            'discount' => '39.80',
            'total' => '159.20',
        ]);
    }

    /**
     * Test 11: Payment status endpoint resolves payment details by reference.
     */
    public function test_payment_status_endpoint_resolves_payment_by_reference(): void
    {
        $subscription = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriber->id,
            'plan_id' => $this->executivePlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'BDT',
            'status' => 'incomplete',
        ]);

        $invoice = Invoice::create([
            'id' => (string) Str::uuid(),
            'subscription_id' => $subscription->id,
            'invoice_number' => 'INV-2026-TEST01',
            'subtotal' => 22000.00,
            'discount' => 0.00,
            'tax' => 0.00,
            'total' => 22000.00,
            'currency' => 'BDT',
            'status' => 'unpaid',
            'due_date' => now()->addDays(7),
        ]);

        $payment = Payment::create([
            'id' => (string) Str::uuid(),
            'invoice_id' => $invoice->id,
            'payment_method' => 'bkash',
            'gateway_key' => 'bkash',
            'provider_reference' => 'BK_STATUS_REF_01',
            'amount' => 22000.00,
            'currency' => 'BDT',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->subscriber)
            ->getJson('/api/v1/billing/checkout/status/BK_STATUS_REF_01');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reference', 'BK_STATUS_REF_01')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.invoice.invoice_number', 'INV-2026-TEST01');
    }

    /**
     * Test 12: Payment status enforces subscriber tenant isolation (returns 403 for other users).
     */
    public function test_payment_status_endpoint_enforces_subscriber_tenant_isolation_403(): void
    {
        $subscription = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriber->id,
            'plan_id' => $this->executivePlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'BDT',
            'status' => 'incomplete',
        ]);

        $invoice = Invoice::create([
            'id' => (string) Str::uuid(),
            'subscription_id' => $subscription->id,
            'invoice_number' => 'INV-2026-SECURE',
            'subtotal' => 22000.00,
            'discount' => 0.00,
            'tax' => 0.00,
            'total' => 22000.00,
            'currency' => 'BDT',
            'status' => 'unpaid',
            'due_date' => now()->addDays(7),
        ]);

        $payment = Payment::create([
            'id' => (string) Str::uuid(),
            'invoice_id' => $invoice->id,
            'payment_method' => 'bkash',
            'gateway_key' => 'bkash',
            'provider_reference' => 'BK_SECRET_99',
            'amount' => 22000.00,
            'currency' => 'BDT',
            'status' => 'pending',
        ]);

        // Attempt inspection by another subscriber
        $response = $this->actingAs($this->otherSubscriber)
            ->getJson('/api/v1/billing/checkout/status/BK_SECRET_99');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthorized access to payment status.');
    }

    /**
     * Test 13: Payment retry updates payment method on existing unpaid invoice without duplicating invoices.
     */
    public function test_payment_retry_endpoint_updates_payment_method_without_duplicate_invoices(): void
    {
        $subscription = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriber->id,
            'plan_id' => $this->executivePlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'BDT',
            'status' => 'incomplete',
        ]);

        $invoice = Invoice::create([
            'id' => (string) Str::uuid(),
            'subscription_id' => $subscription->id,
            'invoice_number' => 'INV-RETRY-01',
            'subtotal' => 22000.00,
            'discount' => 0.00,
            'tax' => 0.00,
            'total' => 22000.00,
            'currency' => 'BDT',
            'status' => 'unpaid',
            'due_date' => now()->addDays(7),
        ]);

        $initialInvoicesCount = Invoice::count();

        $response = $this->actingAs($this->subscriber)
            ->postJson('/api/v1/billing/checkout/retry', [
                'invoice_id' => $invoice->id,
                'payment_method' => 'nagad',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment.payment_method', 'nagad');

        // Verify no duplicate invoice was created
        $this->assertEquals($initialInvoicesCount, Invoice::count());
    }

    /**
     * Test 14: Manual payment proof submission prevents duplicate TrxID reuse.
     */
    public function test_manual_bank_proof_submission_prevents_duplicate_trx_id(): void
    {
        $subscription = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriber->id,
            'plan_id' => $this->executivePlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'BDT',
            'status' => 'incomplete',
        ]);

        $invoice = Invoice::create([
            'id' => (string) Str::uuid(),
            'subscription_id' => $subscription->id,
            'invoice_number' => 'INV-PROOF-01',
            'subtotal' => 22000.00,
            'discount' => 0.00,
            'tax' => 0.00,
            'total' => 22000.00,
            'currency' => 'BDT',
            'status' => 'unpaid',
            'due_date' => now()->addDays(7),
        ]);

        $trx = 'TRX_DUPLICATE_CHECK_99';

        $res1 = $this->actingAs($this->subscriber)
            ->postJson('/api/v1/billing/submit-manual-payment', [
                'invoice_id' => $invoice->id,
                'payment_method' => 'bkash',
                'transaction_id' => $trx,
                'sender_account' => '01711223344',
            ]);

        $res1->assertStatus(201)
            ->assertJsonPath('success', true);

        // Attempt submitting same TrxID again
        $res2 = $this->actingAs($this->subscriber)
            ->postJson('/api/v1/billing/submit-manual-payment', [
                'invoice_id' => $invoice->id,
                'payment_method' => 'bkash',
                'transaction_id' => $trx,
            ]);

        $res2->assertStatus(422)
            ->assertJsonPath('error.code', 'DUPLICATE_TRANSACTION');
    }

    /**
     * Test 15: Admin can verify manual payment, crediting invoice and activating subscription.
     */
    public function test_admin_can_verify_manual_bank_payment_and_activate_subscription(): void
    {
        $subscription = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriber->id,
            'plan_id' => $this->executivePlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'BDT',
            'status' => 'incomplete',
        ]);

        $invoice = Invoice::create([
            'id' => (string) Str::uuid(),
            'subscription_id' => $subscription->id,
            'invoice_number' => 'INV-ADMIN-VERIFY-01',
            'subtotal' => 22000.00,
            'discount' => 0.00,
            'tax' => 0.00,
            'total' => 22000.00,
            'currency' => 'BDT',
            'status' => 'unpaid',
            'due_date' => now()->addDays(7),
        ]);

        $payment = Payment::create([
            'id' => (string) Str::uuid(),
            'invoice_id' => $invoice->id,
            'payment_method' => 'manual_bank',
            'gateway_key' => 'manual_bank',
            'provider_reference' => 'WIRE-ADMIN-01',
            'amount' => 22000.00,
            'currency' => 'BDT',
            'status' => 'pending_review',
        ]);

        PaymentProof::create([
            'payment_id' => $payment->id,
            'transaction_id' => 'WIRE-ADMIN-01',
            'sender_account_number' => '1102938475001',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/payments/{$payment->id}/verify", [
                'status' => 'verified',
                'admin_notes' => 'Verified against City Bank account statement by compliance desk.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Assert payment is verified, invoice is paid, and subscription is active
        $this->assertEquals('verified', $payment->fresh()->status);
        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertEquals('active', $subscription->fresh()->status);
    }

    /**
     * Test 16: Payment reconciliation audits unbacked and uncredited ledger transactions.
     */
    public function test_payment_reconciliation_audits_unbacked_and_uncredited_transactions(): void
    {
        $service = app(PaymentReconciliationService::class);

        // Create an unbacked paid invoice (paid with no verified payment)
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->subscriber->id,
            'plan_id' => $this->executivePlan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $unbackedInvoice = Invoice::create([
            'id' => (string) Str::uuid(),
            'subscription_id' => $sub->id,
            'invoice_number' => 'INV-UNBACKED-01',
            'subtotal' => 199.00,
            'discount' => 0.00,
            'tax' => 0.00,
            'total' => 199.00,
            'currency' => 'USD',
            'status' => 'paid',
            'due_date' => now()->subDay(),
        ]);

        $reconciliation = $service->runReconciliation($this->admin);

        $this->assertGreaterThan(0, $reconciliation['discrepancies_found']);
        $this->assertEquals('discrepancies_detected', $reconciliation['status']);

        $types = array_column($reconciliation['discrepancies'], 'type');
        $this->assertContains('unbacked_paid_invoice', $types);
    }
}
