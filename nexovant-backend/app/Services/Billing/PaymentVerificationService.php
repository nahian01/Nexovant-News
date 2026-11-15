<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentVerificationService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected InvoiceService $invoiceService,
        protected SubscriptionLifecycleService $lifecycleService
    ) {}

    /**
     * Verify payment transaction reference from gateway or manual compliance submission.
     */
    public function verifyPayment(string $paymentId, array $payload = [], ?User $adminActor = null): Payment
    {
        return DB::transaction(function () use ($paymentId, $payload, $adminActor) {
            $payment = Payment::with(['invoice.subscription.plan', 'invoice.subscription.user'])
                ->lockForUpdate()
                ->findOrFail($paymentId);

            if ($payment->status === 'verified' || $payment->status === 'paid') {
                return $payment; // Idempotent return
            }

            $gatewayKey = $payment->gateway_key ?: $payment->payment_method;
            $adapter = $this->gatewayManager->getGateway($gatewayKey);

            $trxRef = $payload['transaction_id'] ?? $payment->provider_reference;

            // Execute adapter verification
            $result = $adapter->verifyPayment($trxRef, array_merge($payload, [
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
            ]));

            if (!$result->isVerified) {
                $payment->update([
                    'status' => 'failed',
                    'failure_reason' => $result->failureReason ?? 'Verification failed at gateway.',
                ]);

                throw ValidationException::withMessages([
                    'payment' => [$result->failureReason ?? 'Payment verification was declined.'],
                ]);
            }

            // Mark Payment as verified / paid
            $payment->update([
                'status' => 'verified',
                'gateway_charge_id' => $result->gatewayChargeId ?? $payment->gateway_charge_id,
                'verified_by_user_id' => $adminActor?->id,
                'verified_at' => now(),
                'raw_response' => array_merge((array) $payment->raw_response, $result->rawResponse),
            ]);

            // Mark Invoice as paid
            $invoice = $payment->invoice;
            $this->invoiceService->markPaid($invoice);

            // Activate Subscription
            $subscription = $invoice->subscription;
            $this->lifecycleService->activateSubscription(
                $subscription,
                $payment,
                $adminActor,
                "Verified via {$gatewayKey} (Ref: {$trxRef})"
            );

            AuditLog::create([
                'user_id' => $adminActor?->id ?? $subscription->user_id,
                'action' => 'payment.verified',
                'resource_type' => 'Payment',
                'resource_id' => $payment->id,
                'new_values' => [
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'gateway' => $gatewayKey,
                    'status' => 'verified',
                ],
                'created_at' => now(),
            ]);

            return $payment->fresh(['invoice', 'proof']);
        });
    }

    /**
     * Submit manual payment proof for compliance review (e.g. bKash / Nagad / Bank wire).
     */
    public function submitManualProof(User $user, Invoice $invoice, array $data): Payment
    {
        $trxId = trim($data['transaction_id']);
        $method = trim($data['payment_method']);

        // Check duplicate transaction ID
        $duplicate = PaymentProof::where('transaction_id', $trxId)->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'transaction_id' => ['This transaction reference ID has already been submitted for verification.'],
            ]);
        }

        return DB::transaction(function () use ($user, $invoice, $method, $trxId, $data) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'payment_method' => $method,
                'gateway_key' => str_starts_with($method, 'manual_') ? 'manual_bank' : $method,
                'provider_reference' => $trxId,
                'amount' => $invoice->total,
                'currency' => $invoice->currency,
                'status' => 'pending_review',
            ]);

            PaymentProof::create([
                'payment_id' => $payment->id,
                'transaction_id' => $trxId,
                'sender_account_number' => $data['sender_account'] ?? null,
                'metadata' => [
                    'user_id' => $user->id,
                    'submitted_at' => now()->toIso8601String(),
                    'notes' => $data['notes'] ?? null,
                ]
            ]);

            return $payment->load('proof');
        });
    }
}
