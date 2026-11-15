<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\User;
use App\Services\Workspace\SubscriberNotificationService;
use Illuminate\Support\Str;

class SubscriptionLifecycleService
{
    public function __construct(
        protected ?SubscriberNotificationService $notificationService = null
    ) {}

    /**
     * Activate a subscription after verified payment or for a free tier.
     */
    public function activateSubscription(
        Subscription $subscription,
        ?Payment $payment = null,
        ?User $actor = null,
        string $reason = 'Payment verified and credited'
    ): void {
        $previousStatus = $subscription->status;
        $plan = $subscription->plan;

        $durationDays = $subscription->billing_cycle === 'annual' ? 365 : 30;
        $startsAt = now();
        $endsAt = now()->addDays($durationDays);

        $subscription->update([
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'next_renewal_at' => $endsAt,
            'paused_at' => null,
            'grace_period_ends_at' => null,
        ]);

        $this->recordTransition(
            $subscription,
            $previousStatus,
            'active',
            $reason,
            $actor?->id
        );

        // Assign 'subscriber' role to user
        $user = $subscription->user;
        if ($user) {
            $user->roles()->syncWithoutDetaching(
                \App\Models\Role::where('name', 'subscriber')->pluck('id')->toArray()
            );

            // Send notification
            $this->sendLifecycleAlert(
                $user,
                'Subscription Activated',
                "Your {$plan->name} subscription is now active through " . $endsAt->format('M d, Y') . '.',
                'subscription_activated'
            );
        }
    }

    /**
     * Start a free trial for an eligible plan.
     */
    public function startTrial(User $user, Plan $plan, int $trialDays = 14): Subscription
    {
        $startsAt = now();
        $trialEndsAt = now()->addDays($trialDays);

        $subscription = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'currency' => 'USD',
            'status' => 'trialing',
            'starts_at' => $startsAt,
            'ends_at' => $trialEndsAt,
            'trial_ends_at' => $trialEndsAt,
            'next_renewal_at' => $trialEndsAt,
        ]);

        $this->recordTransition(
            $subscription,
            'none',
            'trialing',
            "Started {$trialDays}-day complimentary trial of {$plan->name}",
            $user->id
        );

        $this->sendLifecycleAlert(
            $user,
            'Complimentary Trial Active',
            "Welcome to your {$trialDays}-day trial of Nexovant {$plan->name}. Explore verified intelligence and custom monitors.",
            'trial_started'
        );

        return $subscription;
    }

    /**
     * Cancel subscription.
     */
    public function cancelSubscription(Subscription $subscription, User $actor, bool $atPeriodEnd = true, string $reason = 'Requested by subscriber'): void
    {
        $previousStatus = $subscription->status;
        $newStatus = $atPeriodEnd ? 'cancel_at_period_end' : 'canceled';

        $subscription->update([
            'status' => $newStatus,
            'canceled_at' => now(),
            'next_renewal_at' => null,
        ]);

        $this->recordTransition(
            $subscription,
            $previousStatus,
            $newStatus,
            $reason,
            $actor->id
        );

        if ($subscription->user) {
            $this->sendLifecycleAlert(
                $subscription->user,
                'Subscription Cancellation Notice',
                $atPeriodEnd
                    ? "Your subscription cancellation is confirmed. You retain full access until " . ($subscription->ends_at?->format('M d, Y') ?? 'end of period') . "."
                    : "Your subscription has been canceled immediately.",
                'subscription_canceled'
            );
        }
    }

    /**
     * Reactivate a subscription that was scheduled to cancel.
     */
    public function reactivateSubscription(Subscription $subscription, User $actor): void
    {
        if ($subscription->status !== 'cancel_at_period_end') {
            throw new \InvalidArgumentException('Only subscriptions scheduled for cancellation at period end can be reactivated.');
        }

        $previousStatus = $subscription->status;
        $subscription->update([
            'status' => 'active',
            'canceled_at' => null,
            'next_renewal_at' => $subscription->ends_at,
        ]);

        $this->recordTransition(
            $subscription,
            $previousStatus,
            'active',
            'Subscriber reactivated auto-renewal.',
            $actor->id
        );

        if ($subscription->user) {
            $this->sendLifecycleAlert(
                $subscription->user,
                'Subscription Reactivated',
                "Auto-renewal has been successfully reactivated for your {$subscription->plan->name} plan.",
                'subscription_reactivated'
            );
        }
    }

    /**
     * Mark a subscription as past due after failed renewal.
     */
    public function markPastDue(Subscription $subscription, string $reason = 'Recurring payment charge failed'): void
    {
        $previousStatus = $subscription->status;
        $graceDays = $subscription->plan->grace_period_days ?? 3;
        $graceEndsAt = now()->addDays($graceDays);

        $subscription->update([
            'status' => 'past_due',
            'grace_period_ends_at' => $graceEndsAt,
        ]);

        $this->recordTransition(
            $subscription,
            $previousStatus,
            'past_due',
            "{$reason}. Grace period active until " . $graceEndsAt->format('Y-m-d H:i'),
            null
        );

        if ($subscription->user) {
            $this->sendLifecycleAlert(
                $subscription->user,
                'Payment Overdue Notice',
                "Your subscription renewal could not be processed. Please update your payment method before " . $graceEndsAt->format('M d, Y') . " to prevent service interruption.",
                'payment_overdue'
            );
        }
    }

    /**
     * Expire an overdue subscription whose grace period elapsed.
     */
    public function expireSubscription(Subscription $subscription, string $reason = 'Grace period expired without payment'): void
    {
        $previousStatus = $subscription->status;

        $subscription->update([
            'status' => 'expired',
            'ends_at' => now(),
            'next_renewal_at' => null,
        ]);

        $this->recordTransition(
            $subscription,
            $previousStatus,
            'expired',
            $reason,
            null
        );

        if ($subscription->user) {
            $this->sendLifecycleAlert(
                $subscription->user,
                'Subscription Expired',
                "Your subscription to {$subscription->plan->name} has expired. Upgrade your plan to restore full executive features.",
                'subscription_expired'
            );
        }
    }

    /**
     * Helper to log transition in SubscriptionHistory & AuditLog.
     */
    public function recordTransition(
        Subscription $subscription,
        string $previousStatus,
        string $newStatus,
        string $reason,
        ?string $changedByUserId = null
    ): SubscriptionHistory {
        $history = SubscriptionHistory::create([
            'id' => (string) Str::uuid(),
            'subscription_id' => $subscription->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'changed_by_user_id' => $changedByUserId,
            'created_at' => now(),
        ]);

        AuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $changedByUserId ?? $subscription->user_id,
            'action' => 'subscription.status_change',
            'resource_type' => 'Subscription',
            'resource_id' => $subscription->id,
            'previous_values' => ['status' => $previousStatus],
            'new_values' => ['status' => $newStatus, 'reason' => $reason],
            'created_at' => now(),
        ]);

        return $history;
    }

    protected function sendLifecycleAlert(User $user, string $title, string $message, string $category): void
    {
        try {
            if ($this->notificationService) {
                $this->notificationService->sendNotification($user, [
                    'title' => $title,
                    'message' => $message,
                    'category' => $category,
                    'priority' => 'high',
                ]);
            }
        } catch (\Throwable) {
            // Notification failures do not block subscription transactions
        }
    }
}
