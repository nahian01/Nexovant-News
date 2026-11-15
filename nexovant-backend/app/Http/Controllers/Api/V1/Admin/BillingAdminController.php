<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AuditLog;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\PaymentReconciliationRun;
use App\Models\Plan;
use App\Models\PlanEntitlement;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\SubscriptionEntitlementOverride;
use App\Models\SubscriptionHistory;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\GatewayRegistryService;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\PaymentReconciliationService;
use App\Services\Billing\PaymentVerificationService;
use App\Services\Billing\RefundService;
use App\Services\Billing\SubscriptionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingAdminController
{
    public function __construct(
        protected GatewayRegistryService $gatewayRegistryService,
        protected EntitlementService $entitlementService,
        protected SubscriptionLifecycleService $lifecycleService,
        protected RefundService $refundService,
        protected PaymentReconciliationService $reconciliationService,
        protected PaymentVerificationService $verificationService,
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Financial and subscription telemetry dashboard overview.
     */
    public function overview(): JsonResponse
    {
        // 1. Subscription counts by status
        $statusCounts = Subscription::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $activeCount = $statusCounts['active'] ?? 0;
        $trialCount = $statusCounts['trialing'] ?? 0;
        $pastDueCount = $statusCounts['past_due'] ?? 0;
        $canceledCount = ($statusCounts['canceled'] ?? 0) + ($statusCounts['cancel_at_period_end'] ?? 0);
        $totalSubscribers = array_sum($statusCounts);

        // 2. MRR (Monthly Recurring Revenue) calculation
        // Calculate USD and BDT normalized MRR from active subscriptions
        $activeSubs = Subscription::with('plan')->where('status', 'active')->get();
        $mrrUsd = 0.0;
        $mrrBdt = 0.0;

        foreach ($activeSubs as $sub) {
            $plan = $sub->plan;
            if (!$plan) continue;

            if ($sub->currency === 'BDT') {
                $monthly = $sub->billing_cycle === 'annual'
                    ? ((float) $plan->price_bdt_annual / 12)
                    : (float) $plan->price_bdt_monthly;
                $mrrBdt += $monthly;
            } else {
                $monthly = $sub->billing_cycle === 'annual'
                    ? ((float) $plan->price_usd_annual / 12)
                    : (float) $plan->price_usd_monthly;
                $mrrUsd += $monthly;
            }
        }

        $arrUsd = $mrrUsd * 12;
        $arrBdt = $mrrBdt * 12;

        // 3. Churn rate (canceled in last 30 days vs total active 30 days ago)
        $canceledLast30Days = Subscription::whereIn('status', ['canceled', 'expired'])
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();
        $churnRate = $totalSubscribers > 0 ? round(($canceledLast30Days / $totalSubscribers) * 100, 2) : 0.0;

        // 4. Lifetime collected revenue
        $revenueUsd = (float) Payment::whereIn('status', ['verified', 'paid'])
            ->where('currency', 'USD')
            ->sum('amount');
        $revenueBdt = (float) Payment::whereIn('status', ['verified', 'paid'])
            ->where('currency', 'BDT')
            ->sum('amount');

        // 5. Gateway volume breakdown
        $gatewayVolume = Payment::whereIn('status', ['verified', 'paid'])
            ->select('gateway_key', DB::raw('count(*) as count'), DB::raw('sum(amount) as total'))
            ->groupBy('gateway_key')
            ->get();

        // 6. Recent transactions
        $recentPayments = Payment::with(['invoice.subscription.user', 'proof', 'verifiedBy'])
            ->latest('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'metrics' => [
                    'mrr_usd' => round($mrrUsd, 2),
                    'mrr_bdt' => round($mrrBdt, 2),
                    'arr_usd' => round($arrUsd, 2),
                    'arr_bdt' => round($arrBdt, 2),
                    'total_revenue_usd' => round($revenueUsd, 2),
                    'total_revenue_bdt' => round($revenueBdt, 2),
                    'churn_rate_pct' => $churnRate,
                    'subscribers' => [
                        'active' => $activeCount,
                        'trialing' => $trialCount,
                        'past_due' => $pastDueCount,
                        'canceled' => $canceledCount,
                        'total' => $totalSubscribers,
                    ],
                ],
                'gateway_volume' => $gatewayVolume,
                'recent_payments' => $recentPayments,
            ],
        ]);
    }

    /**
     * List all plans including inactive ones with entitlements.
     */
    public function plans(): JsonResponse
    {
        $plans = Plan::with('entitlements')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * Create new subscription plan with entitlements.
     */
    public function storePlan(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:50', 'unique:plans,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_usd_monthly' => ['required', 'numeric', 'min:0'],
            'price_usd_annual' => ['required', 'numeric', 'min:0'],
            'price_bdt_monthly' => ['required', 'numeric', 'min:0'],
            'price_bdt_annual' => ['required', 'numeric', 'min:0'],
            'trial_period_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_popular' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'entitlements' => ['nullable', 'array'],
        ]);

        $admin = $request->user();

        $plan = DB::transaction(function () use ($request, $admin) {
            $plan = Plan::create($request->only([
                'name', 'slug', 'description',
                'price_usd_monthly', 'price_usd_annual',
                'price_bdt_monthly', 'price_bdt_annual',
                'trial_period_days', 'is_active', 'is_popular', 'sort_order'
            ]));

            if ($request->has('entitlements')) {
                foreach ($request->input('entitlements', []) as $featureKey => $item) {
                    $val = is_array($item) ? ($item['value'] ?? '') : $item;
                    $type = is_array($item) ? ($item['type'] ?? 'string') : 'string';

                    PlanEntitlement::create([
                        'id' => (string) Str::uuid(),
                        'plan_id' => $plan->id,
                        'feature_key' => $featureKey,
                        'value_type' => $type,
                        'value' => (string) $val,
                    ]);
                }
            }

            AuditLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => $admin->id,
                'action' => 'admin.plan.created',
                'resource_type' => 'Plan',
                'resource_id' => $plan->id,
                'new_values' => $plan->toArray(),
                'created_at' => now(),
            ]);

            return $plan->load('entitlements');
        });

        return response()->json([
            'success' => true,
            'message' => "Plan '{$plan->name}' created successfully.",
            'data' => $plan,
        ], 201);
    }

    /**
     * Update existing plan and entitlements.
     */
    public function updatePlan(Request $request, string $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);

        $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'slug' => ['sometimes', 'string', 'max:50', 'unique:plans,slug,' . $plan->id],
            'description' => ['nullable', 'string', 'max:500'],
            'price_usd_monthly' => ['sometimes', 'numeric', 'min:0'],
            'price_usd_annual' => ['sometimes', 'numeric', 'min:0'],
            'price_bdt_monthly' => ['sometimes', 'numeric', 'min:0'],
            'price_bdt_annual' => ['sometimes', 'numeric', 'min:0'],
            'trial_period_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_popular' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
            'entitlements' => ['nullable', 'array'],
        ]);

        $admin = $request->user();

        DB::transaction(function () use ($request, $plan, $admin) {
            $plan->update($request->only([
                'name', 'slug', 'description',
                'price_usd_monthly', 'price_usd_annual',
                'price_bdt_monthly', 'price_bdt_annual',
                'trial_period_days', 'is_active', 'is_popular', 'sort_order'
            ]));

            if ($request->has('entitlements')) {
                // Delete existing and rewrite
                PlanEntitlement::where('plan_id', $plan->id)->delete();

                foreach ($request->input('entitlements', []) as $featureKey => $item) {
                    $val = is_array($item) ? ($item['value'] ?? '') : $item;
                    $type = is_array($item) ? ($item['type'] ?? 'string') : 'string';

                    PlanEntitlement::create([
                        'id' => (string) Str::uuid(),
                        'plan_id' => $plan->id,
                        'feature_key' => $featureKey,
                        'value_type' => $type,
                        'value' => (string) $val,
                    ]);
                }
            }

            AuditLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => $admin->id,
                'action' => 'admin.plan.updated',
                'resource_type' => 'Plan',
                'resource_id' => $plan->id,
                'new_values' => $plan->toArray(),
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => "Plan '{$plan->name}' updated successfully.",
            'data' => $plan->fresh()->load('entitlements'),
        ]);
    }

    /**
     * Paginated list of subscriptions with filtering.
     */
    public function subscriptions(Request $request): JsonResponse
    {
        $query = Subscription::with(['user', 'plan', 'invoices' => fn($q) => $q->latest()->limit(1)]);

        if ($request->has('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('plan_id')) {
            $query->where('plan_id', $request->input('plan_id'));
        }

        if ($request->has('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->whereHas('user', function ($q) use ($term) {
                $q->where('name', 'like', $term)->orWhere('email', 'like', $term);
            });
        }

        $subs = $query->latest('created_at')->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $subs->items(),
            'meta' => [
                'current_page' => $subs->currentPage(),
                'last_page' => $subs->lastPage(),
                'total' => $subs->total(),
            ],
        ]);
    }

    /**
     * Subscription details with full history, invoices, and overrides.
     */
    public function subscriptionDetails(string $id): JsonResponse
    {
        $sub = Subscription::with([
            'user',
            'plan.entitlements',
            'invoices.payments',
            'history.changedBy',
            'entitlementOverrides.grantedBy',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $sub,
        ]);
    }

    /**
     * Admin manual status change for a subscription.
     */
    public function updateSubscriptionStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:active,paused,canceled,past_due,expired,trialing'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $admin = $request->user();
        $sub = Subscription::with(['plan', 'user'])->findOrFail($id);
        $oldStatus = $sub->status;
        $newStatus = $request->input('status');
        $reason = $request->input('reason');

        DB::transaction(function () use ($sub, $oldStatus, $newStatus, $reason, $admin) {
            $updateData = ['status' => $newStatus];

            if ($newStatus === 'active' && !$sub->starts_at) {
                $updateData['starts_at'] = now();
                $updateData['ends_at'] = now()->addDays($sub->billing_cycle === 'annual' ? 365 : 30);
                $updateData['next_renewal_at'] = $updateData['ends_at'];
            } elseif ($newStatus === 'canceled') {
                $updateData['canceled_at'] = now();
                $updateData['next_renewal_at'] = null;
            }

            $sub->update($updateData);

            SubscriptionHistory::create([
                'id' => (string) Str::uuid(),
                'subscription_id' => $sub->id,
                'previous_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => "Manual admin override: {$reason}",
                'changed_by_user_id' => $admin->id,
                'created_at' => now(),
            ]);

            AuditLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => $admin->id,
                'action' => 'admin.subscription.status_change',
                'resource_type' => 'Subscription',
                'resource_id' => $sub->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => $newStatus, 'reason' => $reason],
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => "Subscription status transitioned to '{$newStatus}'.",
            'data' => $sub->fresh(),
        ]);
    }

    /**
     * Grant a custom entitlement override to a subscriber.
     */
    public function grantOverride(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'feature_key' => ['required', 'string', 'max:100'],
            'value' => ['required', 'string', 'max:100'],
            'reason' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $sub = Subscription::with('user')->findOrFail($id);
        $admin = $request->user();

        $expiresAt = $request->filled('expires_at') ? new \DateTime($request->input('expires_at')) : null;

        $override = $this->entitlementService->grantOverride(
            $admin,
            $sub->user,
            $request->input('feature_key'),
            $request->input('value'),
            $request->input('reason'),
            $expiresAt
        );

        return response()->json([
            'success' => true,
            'message' => "Entitlement override for '{$override->feature_key}' granted successfully.",
            'data' => $override,
        ], 201);
    }

    /**
     * Revoke an entitlement override.
     */
    public function revokeOverride(string $overrideId, Request $request): JsonResponse
    {
        $override = SubscriptionEntitlementOverride::findOrFail($overrideId);
        $admin = $request->user();

        $override->delete();

        AuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'action' => 'admin.entitlement.override_revoked',
            'resource_type' => 'SubscriptionEntitlementOverride',
            'resource_id' => $overrideId,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Entitlement override revoked.',
        ]);
    }

    /**
     * Paginated invoice list with filtering.
     */
    public function invoices(Request $request): JsonResponse
    {
        $query = Invoice::with(['subscription.user', 'subscription.plan', 'payments']);

        if ($request->has('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', $term)
                  ->orWhere('billing_name', 'like', $term)
                  ->orWhere('billing_email', 'like', $term);
            });
        }

        $invoices = $query->latest('created_at')->paginate((int) $request->query('per_page', 20));

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
     * View invoice line items, payments, and credit notes.
     */
    public function invoiceDetails(string $id): JsonResponse
    {
        $invoice = Invoice::with(['subscription.user', 'subscription.plan', 'items', 'payments.proof', 'payments.refunds'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    /**
     * List payment gateways with capability registry and operational health.
     */
    public function gateways(): JsonResponse
    {
        $gateways = $this->gatewayRegistryService->getGatewayRegistry();

        return response()->json([
            'success' => true,
            'data' => $gateways,
        ]);
    }

    /**
     * Update gateway configuration, credentials, or toggle active status.
     */
    public function updateGateway(Request $request, string $key): JsonResponse
    {
        $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'test_mode' => ['sometimes', 'boolean'],
            'merchant_verified' => ['sometimes', 'boolean'],
            'credentials' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ]);

        $gateway = $this->gatewayRegistryService->updateGateway($request->user(), $key, $request->all());

        return response()->json([
            'success' => true,
            'message' => "Gateway '{$key}' configuration updated.",
            'data' => $gateway,
        ]);
    }

    /**
     * Test gateway connectivity.
     */
    public function testGateway(string $key): JsonResponse
    {
        $result = $this->gatewayRegistryService->testConnection($key);

        return response()->json($result);
    }

    /**
     * List refunds and disputes.
     */
    public function refunds(Request $request): JsonResponse
    {
        $refunds = Refund::with(['payment.invoice.subscription.user', 'processedBy'])
            ->latest('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $refunds->items(),
            'meta' => [
                'current_page' => $refunds->currentPage(),
                'last_page' => $refunds->lastPage(),
                'total' => $refunds->total(),
            ],
        ]);
    }

    /**
     * Process full or partial refund for a verified payment.
     */
    public function processRefund(Request $request): JsonResponse
    {
        $request->validate([
            'payment_id' => ['required', 'exists:payments,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payment = Payment::with('invoice.subscription')->findOrFail($request->input('payment_id'));

        $refund = $this->refundService->processRefund(
            $request->user(),
            $payment,
            (float) $request->input('amount'),
            $request->input('reason'),
            $request->input('notes')
        );

        return response()->json([
            'success' => true,
            'message' => "Refund of {$refund->currency} {$refund->amount} processed successfully.",
            'data' => $refund,
        ], 201);
    }

    /**
     * List promotional coupons.
     */
    public function coupons(Request $request): JsonResponse
    {
        $coupons = Coupon::latest('created_at')->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $coupons->items(),
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'total' => $coupons->total(),
            ],
        ]);
    }

    /**
     * Create promotional coupon.
     */
    public function storeCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'name' => ['required', 'string', 'max:100'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'in:USD,BDT'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'eligible_plans' => ['nullable', 'array'],
            'eligible_countries' => ['nullable', 'array'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data = $request->all();
        $data['code'] = strtoupper(trim($data['code']));
        $data['id'] = (string) Str::uuid();

        $coupon = Coupon::create($data);

        return response()->json([
            'success' => true,
            'message' => "Coupon '{$coupon->code}' created.",
            'data' => $coupon,
        ], 201);
    }

    /**
     * Toggle coupon active state.
     */
    public function toggleCoupon(string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update(['is_active' => !$coupon->is_active]);

        return response()->json([
            'success' => true,
            'message' => "Coupon status set to " . ($coupon->is_active ? 'active' : 'inactive') . '.',
            'data' => $coupon,
        ]);
    }

    /**
     * Execute full payment reconciliation run.
     */
    public function reconcile(Request $request): JsonResponse
    {
        $gatewayKey = $request->input('gateway_key');
        $result = $this->reconciliationService->runReconciliation($request->user(), $gatewayKey);

        return response()->json([
            'success' => true,
            'message' => 'Reconciliation run completed.',
            'data' => $result,
        ]);
    }

    /**
     * List historical reconciliation runs.
     */
    public function reconciliationHistory(): JsonResponse
    {
        $runs = PaymentReconciliationRun::with('executedBy')
            ->latest('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $runs,
        ]);
    }
}
