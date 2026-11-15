<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SubscriptionProrationService
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected SubscriptionLifecycleService $lifecycleService
    ) {}

    /**
     * Calculate proration details for upgrading or changing plans.
     */
    public function calculateProration(Subscription $subscription, Plan $targetPlan, string $newInterval = 'monthly'): array
    {
        $currentPlan = $subscription->plan;
        $currency = $subscription->currency;

        $targetPrice = $currency === 'BDT'
            ? ($newInterval === 'annual' ? (float) $targetPlan->price_bdt_annual : (float) $targetPlan->price_bdt_monthly)
            : ($newInterval === 'annual' ? (float) $targetPlan->price_usd_annual : (float) $targetPlan->price_usd_monthly);

        // If currently on Free plan or expired, no credit to prorate
        if (!$subscription->isActive() || $currentPlan->slug === 'free' || !$subscription->ends_at) {
            return [
                'type' => 'new_plan',
                'unused_credit' => 0.0,
                'target_price' => $targetPrice,
                'amount_due' => $targetPrice,
                'currency' => $currency,
                'effective_date' => now()->toIso8601String(),
            ];
        }

        // Calculate unused fraction of current billing cycle
        $totalCycleDays = max(1, $subscription->starts_at ? $subscription->starts_at->diffInDays($subscription->ends_at) : 30);
        $remainingDays = max(0, now()->diffInDays($subscription->ends_at, false));

        $currentPrice = $currency === 'BDT'
            ? ($subscription->billing_cycle === 'annual' ? (float) $currentPlan->price_bdt_annual : (float) $currentPlan->price_bdt_monthly)
            : ($subscription->billing_cycle === 'annual' ? (float) $currentPlan->price_usd_annual : (float) $currentPlan->price_usd_monthly);

        $fractionRemaining = min(1.0, max(0.0, $remainingDays / $totalCycleDays));
        $unusedCredit = round($currentPrice * $fractionRemaining, 2);

        $amountDue = max(0.0, round($targetPrice - $unusedCredit, 2));

        $isUpgrade = $targetPrice >= $currentPrice;

        return [
            'type' => $isUpgrade ? 'upgrade' : 'downgrade',
            'current_plan' => $currentPlan->name,
            'target_plan' => $targetPlan->name,
            'current_price' => $currentPrice,
            'target_price' => $targetPrice,
            'remaining_days' => $remainingDays,
            'unused_credit' => $unusedCredit,
            'amount_due' => $amountDue,
            'currency' => $currency,
            'new_interval' => $newInterval,
        ];
    }

    /**
     * Execute an immediate plan change with proration invoice.
     */
    public function executePlanChange(User $user, Subscription $subscription, Plan $targetPlan, string $newInterval = 'monthly'): array
    {
        $proration = $this->calculateProration($subscription, $targetPlan, $newInterval);

        $isFreeOrFullyCredited = $proration['amount_due'] == 0;

        $invoice = $this->invoiceService->createInvoice(
            $subscription,
            $proration['target_price'],
            $proration['unused_credit'],
            0.0,
            null,
            ['name' => $user->name, 'email' => $user->email],
            [
                [
                    'description' => "Plan Change to {$targetPlan->name} ({$newInterval})",
                    'quantity' => 1,
                    'unit_price' => $proration['target_price'],
                    'amount' => $proration['target_price'],
                ]
            ]
        );

        if ($isFreeOrFullyCredited) {
            $subscription->update([
                'plan_id' => $targetPlan->id,
                'billing_cycle' => $newInterval,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addDays($newInterval === 'annual' ? 365 : 30),
                'next_renewal_at' => now()->addDays($newInterval === 'annual' ? 365 : 30),
            ]);

            $this->invoiceService->markPaid($invoice);
            $this->lifecycleService->recordTransition(
                $subscription,
                $subscription->status,
                'active',
                "Upgraded to {$targetPlan->name} with full unused credit applied.",
                $user->id
            );
        } else {
            // Pending payment for the prorated difference
            $subscription->update([
                'plan_id' => $targetPlan->id,
                'billing_cycle' => $newInterval,
                'prorated_credit' => $proration['unused_credit'],
            ]);
        }

        return [
            'success' => true,
            'proration' => $proration,
            'invoice' => $invoice,
            'immediate_active' => $isFreeOrFullyCredited,
        ];
    }
}
