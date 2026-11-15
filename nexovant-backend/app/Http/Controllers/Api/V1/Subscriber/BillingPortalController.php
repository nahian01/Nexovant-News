<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscriber;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Plan;
use App\Models\SubscriberInteraction;
use App\Models\SubscriberMonitor;
use App\Models\SubscriberWatchlist;
use App\Models\Subscription;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\CouponService;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\PaymentGatewayManager;
use App\Services\Billing\PaymentVerificationService;
use App\Services\Billing\RegionalPaymentSelectionService;
use App\Services\Billing\SubscriptionLifecycleService;
use App\Services\Billing\SubscriptionProrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class BillingPortalController
{
    public function __construct(
        protected EntitlementService $entitlementService,
        protected RegionalPaymentSelectionService $regionalPaymentService,
        protected CheckoutService $checkoutService,
        protected CouponService $couponService,
        protected SubscriptionProrationService $prorationService,
        protected SubscriptionLifecycleService $lifecycleService,
        protected InvoiceService $invoiceService,
        protected PaymentVerificationService $verificationService,
        protected PaymentGatewayManager $gatewayManager
    ) {}

    /**
     * Subscriber billing overview with current plan, lifecycle status, entitlement quotas and usage.
     */
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();

        $subscription = Subscription::with(['plan.entitlements', 'invoices' => function ($q) {
            $q->latest('created_at')->limit(5);
        }])
        ->where('user_id', $user->id)
        ->latest('created_at')
        ->first();

        $entitlements = $this->entitlementService->getEntitlementsSnapshot($user);

        // Calculate real-time usage metrics
        $watchlistsCount = SubscriberWatchlist::where('user_id', $user->id)->count();
        $monitorsCount = SubscriberMonitor::where('user_id', $user->id)->where('is_active', true)->count();
        $todayQueriesCount = SubscriberInteraction::where('user_id', $user->id)
            ->where('interaction_type', 'search')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        $country = $request->header('CF-IPCountry', 'US');
        $currency = $subscription?->currency ?? ($country === 'BD' ? 'BDT' : 'USD');

        $eligibleMethods = $this->regionalPaymentService->getEligiblePaymentMethods(
            $country,
            $currency,
            $subscription?->billing_cycle ?? 'monthly'
        );

        $org = $user->activeOrganization();
        $orgDetails = null;
        if ($org) {
            $orgRole = $user->organizationRole($org);
            $orgSub = $org->activeSubscription();
            $orgDetails = [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
                'role' => $orgRole,
                'is_owner' => $orgRole === 'owner',
                'is_admin' => in_array($orgRole, ['owner', 'admin'], true),
                'seats_count' => $org->members()->count(),
                'max_seats' => $orgSub ? $this->entitlementService->getLimit($user, 'team_seats', 5) : 5,
                'is_pooled' => $entitlements['is_organization_pooled'] ?? false,
                'subscription' => $orgSub ? [
                    'id' => $orgSub->id,
                    'status' => $orgSub->status,
                    'plan_name' => $orgSub->plan?->name,
                    'billing_cycle' => $orgSub->billing_cycle,
                    'next_renewal_at' => $orgSub->next_renewal_at?->toIso8601String(),
                ] : null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'subscription' => $subscription ? [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'is_active' => $subscription->isActive(),
                    'is_trialing' => $subscription->isTrialing(),
                    'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                    'days_left_in_trial' => $subscription->trial_ends_at && $subscription->trial_ends_at->isFuture()
                        ? (int) ceil(now()->diffInRealSeconds($subscription->trial_ends_at) / 86400)
                        : 0,
                    'billing_cycle' => $subscription->billing_cycle,
                    'currency' => $subscription->currency,
                    'starts_at' => $subscription->starts_at?->toIso8601String(),
                    'ends_at' => $subscription->ends_at?->toIso8601String(),
                    'next_renewal_at' => $subscription->next_renewal_at?->toIso8601String(),
                    'canceled_at' => $subscription->canceled_at?->toIso8601String(),
                    'auto_renew' => $subscription->auto_renew && $subscription->status === 'active',
                    'plan' => $subscription->plan ? [
                        'id' => $subscription->plan->id,
                        'name' => $subscription->plan->name,
                        'slug' => $subscription->plan->slug,
                        'tier' => $subscription->plan->tier,
                        'price_usd_monthly' => (float) $subscription->plan->price_usd_monthly,
                        'price_usd_annual' => (float) $subscription->plan->price_usd_annual,
                        'price_bdt_monthly' => (float) $subscription->plan->price_bdt_monthly,
                        'price_bdt_annual' => (float) $subscription->plan->price_bdt_annual,
                    ] : null,
                ] : null,
                'organization' => $orgDetails,
                'entitlements' => $entitlements,
                'usage' => [
                    'watchlists' => [
                        'used' => $watchlistsCount,
                        'limit' => $entitlements['limits']['max_watchlists'],
                    ],
                    'monitors' => [
                        'used' => $monitorsCount,
                        'limit' => $entitlements['limits']['max_active_monitors'],
                    ],
                    'daily_queries' => [
                        'used' => $todayQueriesCount,
                        'limit' => $entitlements['limits']['daily_research_queries'],
                    ],
                ],
                'recent_invoices' => $subscription?->invoices ?? [],
                'eligible_payment_methods' => $eligibleMethods,
            ],
        ]);
    }

    /**
     * Start a complimentary trial for an eligible plan.
     */
    public function startTrial(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => ['required'],
        ]);

        $user = $request->user();

        // Check if user already had a trial or has active subscription
        $existing = Subscription::where('user_id', $user->id)
            ->where(function ($q) {
                $q->where('status', 'active')
                  ->orWhere('status', 'trialing')
                  ->orWhereNotNull('trial_ends_at');
            })
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Free trial is only available once per account, or you already have an active subscription.',
            ], 422);
        }

        $plan = Plan::where('id', $request->input('plan_id'))
            ->orWhere('slug', $request->input('plan_id'))
            ->firstOrFail();

        $trialDays = $plan->trial_period_days > 0 ? $plan->trial_period_days : 14;

        $subscription = $this->lifecycleService->startTrial($user, $plan, $trialDays);

        return response()->json([
            'success' => true,
            'message' => "Your {$trialDays}-day trial of {$plan->name} has started.",
            'data' => $subscription->load('plan.entitlements'),
        ], 201);
    }

    /**
     * List all available plans with entitlements and dual pricing.
     */
    public function plans(Request $request): JsonResponse
    {
        $plans = Plan::with('entitlements')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * List eligible payment methods based on subscriber region and currency.
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $country = strtoupper($request->query('country', 'US'));
        $currency = strtoupper($request->query('currency', $country === 'BD' ? 'BDT' : 'USD'));
        $interval = strtolower($request->query('interval', 'monthly'));

        $methods = $this->regionalPaymentService->getEligiblePaymentMethods($country, $currency, $interval);

        return response()->json([
            'success' => true,
            'country' => $country,
            'currency' => $currency,
            'data' => $methods,
        ]);
    }

    /**
     * Validate promotional coupon code and preview discount.
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'plan_id' => ['required'],
            'country' => ['nullable', 'string', 'size:2'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $plan = Plan::where('id', $request->input('plan_id'))
            ->orWhere('slug', $request->input('plan_id'))
            ->firstOrFail();

        $country = strtoupper($request->input('country', 'US'));
        $subtotal = (float) $request->input('subtotal');

        $result = $this->couponService->validateCoupon(
            $request->input('code'),
            $plan,
            $country,
            $subtotal,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Secure checkout initiation with backend-only pricing recalculation.
     */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => ['required'],
            'billing_cycle' => ['required', 'in:monthly,annual'],
            'currency' => ['required', 'in:BDT,USD,EUR,GBP,CAD'],
            'country' => ['nullable', 'string', 'max:10'],
            'payment_method' => ['required', 'string'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'billing_name' => ['nullable', 'string', 'max:150'],
            'billing_email' => ['nullable', 'email', 'max:150'],
            'billing_address' => ['nullable', 'string', 'max:300'],
        ]);

        $result = $this->checkoutService->processCheckout($request->user(), $request->all());

        return response()->json([
            'success' => true,
            'data' => $result,
        ], 201);
    }

    /**
     * Preview proration before upgrading or downgrading.
     */
    public function previewProration(Request $request): JsonResponse
    {
        $request->validate([
            'target_plan_id' => ['required'],
            'new_interval' => ['nullable', 'in:monthly,annual'],
        ]);

        $user = $request->user();
        $subscription = $user->subscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found to modify.',
            ], 404);
        }

        $targetPlan = Plan::where('id', $request->input('target_plan_id'))
            ->orWhere('slug', $request->input('target_plan_id'))
            ->firstOrFail();

        $newInterval = $request->input('new_interval', $subscription->billing_cycle);

        $proration = $this->prorationService->calculateProration($subscription, $targetPlan, $newInterval);

        return response()->json([
            'success' => true,
            'data' => $proration,
        ]);
    }

    /**
     * Execute subscription plan upgrade or change with proration adjustment.
     */
    public function changePlan(Request $request): JsonResponse
    {
        $request->validate([
            'target_plan_id' => ['required'],
            'new_interval' => ['nullable', 'in:monthly,annual'],
        ]);

        $user = $request->user();
        $subscription = $user->subscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found to modify.',
            ], 404);
        }

        $targetPlan = Plan::where('id', $request->input('target_plan_id'))
            ->orWhere('slug', $request->input('target_plan_id'))
            ->firstOrFail();

        $newInterval = $request->input('new_interval', $subscription->billing_cycle);

        $result = $this->prorationService->executePlanChange($user, $subscription, $targetPlan, $newInterval);

        return response()->json([
            'success' => true,
            'message' => 'Subscription plan update processed.',
            'data' => $result,
        ]);
    }

    /**
     * Cancel subscription at period end or immediately.
     */
    public function cancel(Request $request): JsonResponse
    {
        $request->validate([
            'at_period_end' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $subscription = $user->subscription;

        if (!$subscription || !$subscription->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found to cancel.',
            ], 404);
        }

        $atPeriodEnd = (bool) $request->input('at_period_end', true);
        $reason = $request->input('reason', 'Requested via subscriber portal');

        $this->lifecycleService->cancelSubscription($subscription, $user, $atPeriodEnd, $reason);

        return response()->json([
            'success' => true,
            'message' => $atPeriodEnd
                ? 'Subscription will be canceled at the end of the current billing cycle.'
                : 'Subscription has been canceled immediately.',
            'data' => [
                'status' => $subscription->fresh()->status,
                'ends_at' => $subscription->ends_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Reactivate a subscription that is scheduled to cancel at period end.
     */
    public function reactivate(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscription;

        if (!$subscription || $subscription->status !== 'cancel_at_period_end') {
            return response()->json([
                'success' => false,
                'message' => 'Only subscriptions scheduled for cancellation can be reactivated.',
            ], 422);
        }

        $this->lifecycleService->reactivateSubscription($subscription, $user);

        return response()->json([
            'success' => true,
            'message' => 'Subscription reactivated successfully. Auto-renewal has been restored.',
            'data' => [
                'status' => $subscription->fresh()->status,
            ],
        ]);
    }

    /**
     * List user invoices.
     */
    public function invoices(Request $request): JsonResponse
    {
        $user = $request->user();

        $invoices = Invoice::whereHas('subscription', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->with(['items', 'payments.proof'])
        ->latest('created_at')
        ->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    /**
     * Download or stream PDF receipt for an invoice.
     */
    public function downloadInvoice(string $id, Request $request): Response
    {
        $user = $request->user();

        $invoice = Invoice::with(['subscription.user', 'items', 'payments'])->findOrFail($id);

        // Security check: tenant isolation
        if ($invoice->subscription->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to invoice.',
            ], 403);
        }

        // If PDF does not exist on disk, generate it now
        if (!$invoice->pdf_path || !Storage::disk('local')->exists($invoice->pdf_path)) {
            $this->invoiceService->generatePdf($invoice);
            $invoice->refresh();
        }

        $fullPath = Storage::disk('local')->path($invoice->pdf_path);

        return response()->download($fullPath, "{$invoice->invoice_number}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Submit offline payment proof (MFS or Bank Wire) for manual compliance verification.
     */
    public function submitManualProof(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'payment_method' => ['required', 'string', 'in:manual_bank,manual_bkash,manual_nagad,bkash,nagad'],
            'transaction_id' => ['required', 'string', 'max:100'],
            'sender_account' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $invoice = Invoice::with('subscription')->findOrFail($request->input('invoice_id'));

        // Prevent cross-user payment submissions
        if ($invoice->subscription->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized invoice payment attempt.',
            ], 403);
        }

        // Check for duplicate transaction submission across platform
        $existing = PaymentProof::where('transaction_id', $request->input('transaction_id'))->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DUPLICATE_TRANSACTION',
                    'message' => 'This transaction reference ID has already been submitted for verification.',
                ],
            ], 422);
        }

        $payment = \App\Models\Payment::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'invoice_id' => $invoice->id,
            'gateway_key' => $request->input('payment_method'),
            'payment_method' => $request->input('payment_method'),
            'provider_reference' => $request->input('transaction_id'),
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
            'status' => 'pending_review',
        ]);

        PaymentProof::create([
            'payment_id' => $payment->id,
            'transaction_id' => $request->input('transaction_id'),
            'sender_account_number' => $request->input('sender_account'),
            'notes' => $request->input('notes'),
            'metadata' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'submitted_at' => now()->toIso8601String(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment reference submitted successfully. Our compliance desk will review and credit your subscription.',
            'data' => $payment->load('proof'),
        ], 201);
    }

    /**
     * Get complete checkout options including supported countries, currencies, and eligible payment methods.
     */
    public function checkoutOptions(Request $request): JsonResponse
    {
        $country = strtoupper($request->query('country', $request->header('CF-IPCountry', 'US')));
        $currency = strtoupper($request->query('currency', $country === 'BD' ? 'BDT' : 'USD'));
        $interval = strtolower($request->query('interval', 'monthly'));

        $countries = $this->regionalPaymentService->getSupportedCountries();
        $currencies = $this->regionalPaymentService->getSupportedCurrencies();
        $methods = $this->regionalPaymentService->getEligiblePaymentMethods($country, $currency, $interval);

        return response()->json([
            'success' => true,
            'country' => $country,
            'currency' => $currency,
            'interval' => $interval,
            'countries' => $countries,
            'currencies' => $currencies,
            'eligible_payment_methods' => $methods,
        ]);
    }

    /**
     * Authoritative payment status resolution with strict subscriber ownership enforcement.
     */
    public function paymentStatus(string $reference, Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Locate payment by transaction reference (provider_reference) or payment ID
        $payment = Payment::with(['invoice.subscription.plan', 'proof'])
            ->where('provider_reference', $reference)
            ->orWhere('id', $reference)
            ->first();

        $invoice = null;
        if ($payment) {
            $invoice = $payment->invoice;
        } else {
            // Check if reference is an invoice ID
            $invoice = Invoice::with(['subscription.plan', 'payments.proof'])->find($reference);
            if ($invoice) {
                $payment = $invoice->payments()->latest()->first();
            }
        }

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Payment reference not found.',
            ], 404);
        }

        // 2. Strict tenant and subscriber isolation check
        if ($invoice->subscription && $invoice->subscription->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to payment status.',
            ], 403);
        }

        $subscription = $invoice->subscription;

        return response()->json([
            'success' => true,
            'data' => [
                'reference' => $reference,
                'status' => $payment?->status ?? $invoice->status,
                'invoice' => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status,
                    'subtotal' => (float) $invoice->subtotal,
                    'discount' => (float) $invoice->discount,
                    'total' => (float) $invoice->total,
                    'currency' => $invoice->currency,
                    'due_date' => $invoice->due_date?->toIso8601String(),
                    'paid_at' => $invoice->paid_at?->toIso8601String(),
                ],
                'payment' => $payment ? [
                    'id' => $payment->id,
                    'gateway_key' => $payment->gateway_key,
                    'payment_method' => $payment->payment_method,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'provider_reference' => $payment->provider_reference,
                    'proof' => $payment->proof ? [
                        'transaction_id' => $payment->proof->transaction_id,
                        'sender_account' => $payment->proof->sender_account_number,
                        'created_at' => $payment->proof->created_at?->toIso8601String(),
                    ] : null,
                    'created_at' => $payment->created_at?->toIso8601String(),
                ] : null,
                'subscription' => $subscription ? [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'is_active' => $subscription->isActive(),
                    'plan_name' => $subscription->plan?->name,
                    'billing_cycle' => $subscription->billing_cycle,
                    'next_renewal_at' => $subscription->next_renewal_at?->toIso8601String(),
                ] : null,
                'receipt_url' => $invoice->status === 'paid' ? "/api/v1/billing/invoices/{$invoice->id}/download" : null,
                'instructions' => $payment?->gateway_key === 'manual_bank' ? [
                    'bank' => $invoice->currency === 'BDT' ? 'City Bank PLC' : 'Standard Chartered Bank',
                    'reference' => $invoice->id,
                    'notes' => 'Please include your invoice reference in wire transfer narrative.'
                ] : null,
            ],
        ]);
    }

    /**
     * Safe retry of a failed or pending payment without generating orphan duplicate invoices.
     */
    public function retryPayment(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'payment_method' => ['required', 'string'],
            'return_url' => ['nullable', 'url'],
        ]);

        $user = $request->user();
        $invoice = Invoice::with('subscription')->findOrFail($request->input('invoice_id'));

        // Security check
        if ($invoice->subscription && $invoice->subscription->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to invoice.',
            ], 403);
        }

        if ($invoice->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'This invoice has already been paid.',
            ], 422);
        }

        $gatewayKey = strtolower($request->input('payment_method'));
        $adapter = $this->gatewayManager->getGateway($gatewayKey);

        $intent = new \App\Services\Billing\DTO\PaymentIntentDTO(
            invoiceId: $invoice->id,
            userId: $user->id,
            amount: (float) $invoice->total,
            currency: $invoice->currency,
            paymentMethod: $gatewayKey,
            country: $invoice->subscription->billing_country ?? 'US',
            customerEmail: $user->email,
            customerName: $user->name,
            idempotencyKey: 'retry_' . \Illuminate\Support\Str::random(16),
            returnUrl: $request->input('return_url')
        );

        $initiation = $adapter->initiatePayment($intent);

        $payment = Payment::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'invoice_id' => $invoice->id,
            'payment_method' => $gatewayKey,
            'gateway_key' => $gatewayKey,
            'provider_reference' => $initiation->transactionReference,
            'idempotency_key' => $intent->idempotencyKey,
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
            'status' => $initiation->status,
            'raw_response' => $initiation->rawResponse,
        ]);

        return response()->json([
            'success' => $initiation->success,
            'message' => 'Payment retry initiated.',
            'data' => [
                'invoice' => $invoice,
                'payment' => $payment,
                'transaction_reference' => $initiation->transactionReference,
                'redirect_url' => $initiation->redirectUrl,
                'client_secret' => $initiation->clientSecret,
                'instructions' => $initiation->instructions,
            ],
        ]);
    }
}
