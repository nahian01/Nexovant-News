<?php

namespace Tests\Feature;

use App\Models\AIProvider;
use App\Models\Article;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\NewsSource;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_can_list_intelligence_articles(): void
    {
        $response = $this->getJson('/api/v1/articles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'verification_status',
                        'importance_score',
                        'source',
                    ]
                ],
                'meta',
            ]);
    }

    public function test_can_retrieve_single_article_with_10_dimension_assessment(): void
    {
        $article = Article::where('slug', 'nvidia-custom-silicon-foundry-emea')->firstOrFail();

        $response = $this->getJson("/api/v1/articles/{$article->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'verification_status' => 'confirmed',
                ]
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => ['executive_summary', 'key_takeaways'],
                    'assessment' => [
                        'what_happened',
                        'when_happened',
                        'why_happened',
                        'business_significance',
                        'who_affected',
                        'potential_consequences',
                        'what_to_monitor_next',
                    ]
                ]
            ]);
    }

    public function test_can_list_plans_and_entitlements(): void
    {
        $response = $this->getJson('/api/v1/subscriptions/plans');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'price_bdt_monthly',
                        'price_usd_monthly',
                        'entitlements',
                    ]
                ]
            ]);
    }

    public function test_subscriber_can_checkout_subscription_and_receive_invoice(): void
    {
        $user = User::factory()->create();
        $plan = Plan::where('slug', 'executive')->firstOrFail();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/subscriptions/checkout', [
                'plan_id' => $plan->id,
                'billing_cycle' => 'monthly',
                'currency' => 'BDT',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'subscription' => [
                    'status' => 'pending_payment',
                    'billing_cycle' => 'monthly',
                ],
                'invoice' => [
                    'subtotal' => '999.00',
                    'status' => 'unpaid',
                ]
            ]);
    }

    public function test_subscriber_can_submit_manual_payment_proof(): void
    {
        $user = User::factory()->create();
        $plan = Plan::where('slug', 'executive')->firstOrFail();

        $sub = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'BDT',
            'status' => 'pending_payment',
        ]);

        $invoice = Invoice::create([
            'subscription_id' => $sub->id,
            'invoice_number' => 'INV-TEST-001',
            'subtotal' => 999.00,
            'tax' => 0.00,
            'total' => 999.00,
            'currency' => 'BDT',
            'status' => 'unpaid',
            'due_date' => now()->addDays(3),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/payments/submit', [
                'invoice_id' => $invoice->id,
                'payment_method' => 'manual_bkash',
                'transaction_id' => 'TRX8899AABB',
                'sender_account' => '01711000000',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'payment' => [
                    'status' => 'pending_review',
                    'amount' => '999.00',
                ]
            ]);

        // Duplicate transaction ID submission must fail
        $dupResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/payments/submit', [
                'invoice_id' => $invoice->id,
                'payment_method' => 'manual_bkash',
                'transaction_id' => 'TRX8899AABB',
            ]);

        $dupResponse->assertStatus(422)
            ->assertJson(['error' => ['code' => 'DUPLICATE_TRANSACTION']]);
    }

    public function test_admin_can_approve_payment_and_activate_subscription(): void
    {
        $admin = User::where('email', 'admin@nexovant.si')->firstOrFail();
        $user = User::factory()->create();
        $plan = Plan::where('slug', 'executive')->firstOrFail();

        $sub = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'BDT',
            'status' => 'pending_payment',
        ]);

        $invoice = Invoice::create([
            'subscription_id' => $sub->id,
            'invoice_number' => 'INV-VERIFY-002',
            'subtotal' => 999.00,
            'total' => 999.00,
            'currency' => 'BDT',
            'status' => 'unpaid',
            'due_date' => now()->addDays(3),
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'payment_method' => 'manual_bkash',
            'provider_reference' => 'TRX9988CCDD',
            'amount' => 999.00,
            'currency' => 'BDT',
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/payments/{$payment->id}/verify", [
                'status' => 'verified',
                'admin_notes' => 'TrxID verified against bKash merchant statement.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment verified and subscription activated.',
            ]);

        // Assert database updates
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'verified']);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid']);
        $this->assertDatabaseHas('subscriptions', ['id' => $sub->id, 'status' => 'active']);
    }

    public function test_admin_can_inspect_ai_router_dashboard(): void
    {
        $admin = User::where('email', 'admin@nexovant.si')->firstOrFail();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/ai-router');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'stats' => [
                    'total_connections',
                    'active_connections',
                    'circuit_open_count',
                ],
                'providers',
            ]);
    }
}
