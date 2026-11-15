<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected SubscriptionLifecycleService $lifecycleService
    ) {}

    /**
     * Process full or partial refund for a verified payment.
     */
    public function processRefund(User $admin, Payment $payment, float $amount, string $reason, ?string $notes = null): Refund
    {
        if ($payment->status !== 'verified' && $payment->status !== 'paid') {
            throw ValidationException::withMessages([
                'payment' => ['Only verified/paid transactions can be refunded.'],
            ]);
        }

        $totalRefunded = (float) $payment->refunds()->where('status', 'processed')->sum('amount');
        $maxAvailable = round((float) $payment->amount - $totalRefunded, 2);

        if ($amount > $maxAvailable || $amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ["Refund amount exceeds maximum remaining refundable amount of {$payment->currency} {$maxAvailable}."],
            ]);
        }

        $gatewayKey = $payment->gateway_key ?: $payment->payment_method;
        $adapter = $this->gatewayManager->getGateway($gatewayKey);

        $result = $adapter->processRefund($payment, $amount, $reason);

        return DB::transaction(function () use ($admin, $payment, $amount, $reason, $notes, $result, $totalRefunded) {
            $refund = Refund::create([
                'id' => (string) Str::uuid(),
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'amount' => $amount,
                'currency' => $payment->currency,
                'reason' => $reason,
                'status' => $result->success ? 'processed' : 'failed',
                'gateway_refund_id' => $result->gatewayRefundId,
                'processed_by_user_id' => $admin->id,
                'notes' => $notes,
                'processed_at' => $result->success ? now() : null,
            ]);

            if ($result->success) {
                $isFullRefund = ($totalRefunded + $amount) >= (float) $payment->amount;

                $payment->update([
                    'status' => $isFullRefund ? 'refunded' : 'partially_refunded',
                ]);

                $payment->invoice->update([
                    'status' => $isFullRefund ? 'refunded' : 'partially_refunded',
                ]);

                if ($isFullRefund) {
                    $this->lifecycleService->cancelSubscription(
                        $payment->invoice->subscription,
                        $admin,
                        false,
                        "Canceled due to full refund ({$payment->currency} {$amount}). Reason: {$reason}"
                    );
                }

                AuditLog::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $admin->id,
                    'action' => 'payment.refund',
                    'resource_type' => 'Refund',
                    'resource_id' => $refund->id,
                    'metadata' => [
                        'payment_id' => $payment->id,
                        'amount' => $amount,
                        'currency' => $payment->currency,
                        'is_full_refund' => $isFullRefund,
                        'reason' => $reason,
                    ],
                    'created_at' => now(),
                ]);
            }

            return $refund;
        });
    }
}
