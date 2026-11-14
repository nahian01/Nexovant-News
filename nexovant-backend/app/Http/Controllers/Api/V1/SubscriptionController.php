<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController
{
    /**
     * List all subscription plans and feature entitlements.
     */
    public function plans(): JsonResponse
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
     * Get current user's active subscription and entitlements.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();

        $subscription = Subscription::with(['plan.entitlements', 'invoices' => function ($q) {
            $q->latest()->limit(5);
        }])
        ->where(function ($q) use ($user) {
            $q->where('user_id', $user->id);
            // In organization context, could also check user's organization
        })
        ->latest('created_at')
        ->first();

        return response()->json([
            'success' => true,
            'subscription' => $subscription,
        ]);
    }

    /**
     * Create subscription checkout and generate invoice.
     */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,annual'],
            'currency' => ['required', 'in:BDT,USD'],
        ]);

        $user = $request->user();
        $plan = Plan::findOrFail($request->input('plan_id'));
        $cycle = $request->input('billing_cycle');
        $currency = $request->input('currency');

        // Determine price
        $amount = $currency === 'BDT'
            ? ($cycle === 'annual' ? $plan->price_bdt_annual : $plan->price_bdt_monthly)
            : ($cycle === 'annual' ? $plan->price_usd_annual : $plan->price_usd_monthly);

        // If Free plan, activate immediately
        $isFree = $amount == 0;

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'currency' => $currency,
            'status' => $isFree ? 'active' : 'pending_payment',
            'starts_at' => $isFree ? now() : null,
            'ends_at' => $isFree ? now()->addYear() : null,
        ]);

        $invoice = Invoice::create([
            'subscription_id' => $subscription->id,
            'invoice_number' => 'INV-' . strtoupper(Str::random(8)),
            'subtotal' => $amount,
            'tax' => 0.00,
            'total' => $amount,
            'currency' => $currency,
            'status' => $isFree ? 'paid' : 'unpaid',
            'due_date' => now()->addDays(3),
            'paid_at' => $isFree ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $isFree ? 'Free plan activated.' : 'Invoice generated. Please proceed with payment.',
            'subscription' => $subscription->load('plan'),
            'invoice' => $invoice,
        ], 201);
    }
}
