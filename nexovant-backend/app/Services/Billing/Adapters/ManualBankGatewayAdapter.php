<?php

declare(strict_types=1);

namespace App\Services\Billing\Adapters;

use App\Models\Payment;
use App\Models\PaymentProof;
use App\Services\Billing\Contracts\PaymentGatewayInterface;
use App\Services\Billing\DTO\PaymentInitiationResult;
use App\Services\Billing\DTO\PaymentIntentDTO;
use App\Services\Billing\DTO\PaymentVerificationResult;
use App\Services\Billing\DTO\RefundResult;
use App\Services\Billing\DTO\WebhookResult;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ManualBankGatewayAdapter implements PaymentGatewayInterface
{
    public const GATEWAY_KEY = 'manual_bank';

    public function getGatewayKey(): string
    {
        return self::GATEWAY_KEY;
    }

    public function getDisplayName(): string
    {
        return 'Direct Bank Transfer / Wire Payment';
    }

    public function isAvailableFor(string $country, string $currency): bool
    {
        return true; // Bank wire accepted globally
    }

    public function isConfigured(): bool
    {
        return true; // Institutional banking destination configured
    }

    public function isMerchantVerified(): bool
    {
        return true;
    }

    public function getUnavailabilityReason(): ?string
    {
        return null;
    }

    public function initiatePayment(PaymentIntentDTO $intent): PaymentInitiationResult
    {
        $wireRef = 'WIRE-' . strtoupper(Str::random(10));

        $instructions = $intent->currency === 'BDT'
            ? [
                'bank_name' => 'City Bank PLC',
                'account_name' => 'Nexovant Intelligence Ltd',
                'account_number' => '1102938475001',
                'branch' => 'Gulshan Avenue Branch, Dhaka',
                'routing_number' => '225272648',
                'swift_code' => 'CIBLBDDH',
                'reference' => $intent->invoiceId,
            ]
            : [
                'bank_name' => 'Standard Chartered Bank',
                'account_name' => 'Nexovant Global Corporate Accounts',
                'account_number' => '01-9988223-01',
                'iban' => 'BD28SCBL00000001998822301',
                'swift_bic' => 'SCBLBDDX',
                'reference' => $intent->invoiceId,
            ];

        return new PaymentInitiationResult(
            success: true,
            gatewayKey: self::GATEWAY_KEY,
            transactionReference: $wireRef,
            status: 'pending_review',
            instructions: $instructions,
            rawResponse: ['wireReference' => $wireRef]
        );
    }

    public function verifyPayment(string $transactionReference, array $payload = []): PaymentVerificationResult
    {
        // Check duplicate transaction ID across verified proofs
        $existing = PaymentProof::where('transaction_id', $transactionReference)
            ->whereHas('payment', fn($q) => $q->where('status', 'verified'))
            ->exists();

        if ($existing) {
            return new PaymentVerificationResult(
                isVerified: false,
                gatewayKey: self::GATEWAY_KEY,
                transactionReference: $transactionReference,
                amount: 0.0,
                currency: 'BDT',
                status: 'failed',
                failureReason: 'Transaction ID has already been verified and credited previously.'
            );
        }

        return new PaymentVerificationResult(
            isVerified: true,
            gatewayKey: self::GATEWAY_KEY,
            transactionReference: $transactionReference,
            amount: (float) ($payload['amount'] ?? 0.0),
            currency: strtoupper($payload['currency'] ?? 'BDT'),
            status: 'paid',
            gatewayChargeId: 'WIRE_VERIFIED_' . strtoupper(Str::random(8)),
            rawResponse: $payload
        );
    }

    public function processWebhook(Request $request): WebhookResult
    {
        return new WebhookResult(
            success: false,
            gatewayKey: self::GATEWAY_KEY,
            eventId: 'none',
            eventType: 'manual_gateway',
            errorMessage: 'Direct bank transfer does not receive automated external webhooks.'
        );
    }

    public function processRefund(Payment $payment, float $amount, string $reason): RefundResult
    {
        $refundId = 'MANUAL_REF_' . strtoupper(Str::random(10));

        return new RefundResult(
            success: true,
            gatewayKey: self::GATEWAY_KEY,
            amount: $amount,
            currency: $payment->currency,
            gatewayRefundId: $refundId,
            status: 'processed',
            rawResponse: [
                'refundId' => $refundId,
                'method' => 'bank_wire_reversal',
                'reason' => $reason,
            ]
        );
    }
}
