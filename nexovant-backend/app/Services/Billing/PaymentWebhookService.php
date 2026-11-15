<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Services\Billing\DTO\WebhookResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentWebhookService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected PaymentVerificationService $verificationService,
        protected InvoiceService $invoiceService,
        protected SubscriptionLifecycleService $lifecycleService
    ) {}

    /**
     * Process incoming provider webhook with signature verification and deduplication.
     */
    public function handleWebhook(string $gatewayKey, Request $request): array
    {
        $adapter = $this->gatewayManager->getGateway($gatewayKey);

        // 1. Process signature and event extraction
        $result = $adapter->processWebhook($request);

        if (!$result->success) {
            return [
                'status' => 400,
                'message' => $result->errorMessage ?? 'Webhook verification failed',
            ];
        }

        $idempotencyKey = "{$gatewayKey}_{$result->eventId}";

        // 2. Check for duplicate webhook event
        $existingEvent = PaymentWebhookEvent::where('idempotency_key', $idempotencyKey)
            ->orWhere(function ($q) use ($gatewayKey, $result) {
                $q->where('gateway_key', $gatewayKey)
                  ->where('event_id', $result->eventId);
            })
            ->first();

        if ($existingEvent && $existingEvent->status === 'processed') {
            return [
                'status' => 200,
                'message' => 'Event already processed (Idempotent)',
                'event_id' => $result->eventId,
            ];
        }

        // 3. Log event record
        $webhookRecord = $existingEvent ?: PaymentWebhookEvent::create([
            'id' => (string) Str::uuid(),
            'gateway_key' => $gatewayKey,
            'provider_key' => $gatewayKey,
            'event_id' => $result->eventId,
            'event_type' => $result->eventType,
            'payload' => $request->all(),
            'signature' => $request->header('Stripe-Signature') ?? $request->header('X-Razorpay-Signature'),
            'status' => 'received',
            'idempotency_key' => $idempotencyKey,
        ]);

        // 4. Process event action
        try {
            DB::transaction(function () use ($gatewayKey, $result, $webhookRecord) {
                if ($result->transactionReference) {
                    $payment = Payment::where('provider_reference', $result->transactionReference)
                        ->orWhere('id', $result->transactionReference)
                        ->first();

                    if ($payment) {
                        if ($result->status === 'paid' && $payment->status !== 'verified') {
                            $payment->update([
                                'status' => 'verified',
                                'verified_at' => now(),
                            ]);

                            $this->invoiceService->markPaid($payment->invoice);
                            $this->lifecycleService->activateSubscription(
                                $payment->invoice->subscription,
                                $payment,
                                null,
                                "Automated webhook activation from {$gatewayKey} (Event: {$result->eventId})"
                            );
                        } elseif ($result->status === 'failed') {
                            $payment->update(['status' => 'failed']);
                            $this->lifecycleService->markPastDue(
                                $payment->invoice->subscription,
                                "Webhook notified failed payment: {$result->eventType}"
                            );
                        }
                    }
                }

                $webhookRecord->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                ]);
            });

            return [
                'status' => 200,
                'message' => 'Webhook processed successfully',
                'event_id' => $result->eventId,
            ];
        } catch (\Throwable $e) {
            $webhookRecord->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'message' => 'Internal webhook processing error: ' . $e->getMessage(),
            ];
        }
    }
}
