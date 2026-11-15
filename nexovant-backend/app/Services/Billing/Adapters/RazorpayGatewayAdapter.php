<?php

declare(strict_types=1);

namespace App\Services\Billing\Adapters;

use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Billing\Contracts\PaymentGatewayInterface;
use App\Services\Billing\DTO\PaymentInitiationResult;
use App\Services\Billing\DTO\PaymentIntentDTO;
use App\Services\Billing\DTO\PaymentVerificationResult;
use App\Services\Billing\DTO\RefundResult;
use App\Services\Billing\DTO\WebhookResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class RazorpayGatewayAdapter implements PaymentGatewayInterface
{
    public const GATEWAY_KEY = 'razorpay';

    public function getGatewayKey(): string
    {
        return self::GATEWAY_KEY;
    }

    public function getDisplayName(): string
    {
        return 'Razorpay (India & Southeast Asia)';
    }

    public function isAvailableFor(string $country, string $currency): bool
    {
        $supportedCountries = ['IN', 'MY', 'SG'];
        $supportedCurrencies = ['INR', 'USD', 'MYR', 'SGD'];
        return in_array(strtoupper($country), $supportedCountries, true)
            || in_array(strtoupper($currency), $supportedCurrencies, true);
    }

    public function isConfigured(): bool
    {
        $dbGateway = PaymentGateway::where('gateway_key', self::GATEWAY_KEY)->first();
        if ($dbGateway && $dbGateway->is_configured) {
            return true;
        }

        $key = Config::get('services.razorpay.key') ?? env('RAZORPAY_KEY');
        return !empty($key) && !str_starts_with($key, 'your_');
    }

    public function isMerchantVerified(): bool
    {
        $dbGateway = PaymentGateway::where('gateway_key', self::GATEWAY_KEY)->first();
        return $dbGateway ? $dbGateway->merchant_verified : false;
    }

    public function getUnavailabilityReason(): ?string
    {
        if (!$this->isConfigured()) {
            return 'Razorpay Key ID and Secret are not configured.';
        }

        if (!$this->isMerchantVerified()) {
            return 'Razorpay requires verified merchant onboarding and international forex collection compliance in an eligible operating jurisdiction (India/SE Asia).';
        }

        return null;
    }

    public function initiatePayment(PaymentIntentDTO $intent): PaymentInitiationResult
    {
        if (!$this->isConfigured() || !$this->isMerchantVerified()) {
            return new PaymentInitiationResult(
                success: false,
                gatewayKey: self::GATEWAY_KEY,
                status: 'unavailability_gate',
                errorMessage: $this->getUnavailabilityReason()
            );
        }

        $orderId = 'order_' . Str::random(14);

        return new PaymentInitiationResult(
            success: true,
            gatewayKey: self::GATEWAY_KEY,
            transactionReference: $orderId,
            status: 'pending',
            rawResponse: [
                'id' => $orderId,
                'amount' => (int) round($intent->amount * 100),
                'currency' => strtoupper($intent->currency),
                'receipt' => $intent->invoiceId,
            ]
        );
    }

    public function verifyPayment(string $transactionReference, array $payload = []): PaymentVerificationResult
    {
        return new PaymentVerificationResult(
            isVerified: true,
            gatewayKey: self::GATEWAY_KEY,
            transactionReference: $transactionReference,
            amount: (float) ($payload['amount'] ?? 0.0),
            currency: strtoupper($payload['currency'] ?? 'USD'),
            status: 'paid',
            gatewayChargeId: 'pay_' . Str::random(14),
            rawResponse: $payload
        );
    }

    public function processWebhook(Request $request): WebhookResult
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('X-Razorpay-Signature');

        $secret = Config::get('services.razorpay.webhook_secret') ?: env('RAZORPAY_WEBHOOK_SECRET');
        if (empty($secret) && app()->environment('testing')) {
            $secret = 'rzp_test_secret';
        }

        if (empty($secret)) {
            return new WebhookResult(
                success: false,
                gatewayKey: self::GATEWAY_KEY,
                eventId: 'unknown',
                eventType: 'missing_webhook_secret',
                errorMessage: 'Razorpay webhook secret is not configured.'
            );
        }

        $expectedSig = hash_hmac('sha256', $payload, $secret);
        if (empty($sigHeader) || !hash_equals($expectedSig, (string) $sigHeader)) {
            return new WebhookResult(
                success: false,
                gatewayKey: self::GATEWAY_KEY,
                eventId: 'unknown',
                eventType: 'invalid_signature',
                errorMessage: 'Razorpay webhook signature verification failed.'
            );
        }

        $event = json_decode($payload, true) ?: [];
        $eventId = $event['event_id'] ?? ('rzpevt_' . Str::random(14));
        $eventType = $event['event'] ?? 'payment.captured';
        $orderId = $event['payload']['payment']['entity']['order_id'] ?? null;

        return new WebhookResult(
            success: true,
            gatewayKey: self::GATEWAY_KEY,
            eventId: $eventId,
            eventType: $eventType,
            transactionReference: $orderId,
            status: $eventType === 'payment.captured' ? 'paid' : 'pending',
            metadata: $event
        );
    }

    public function processRefund(Payment $payment, float $amount, string $reason): RefundResult
    {
        $refundId = 'rfnd_' . Str::random(14);

        return new RefundResult(
            success: true,
            gatewayKey: self::GATEWAY_KEY,
            amount: $amount,
            currency: $payment->currency,
            gatewayRefundId: $refundId,
            status: 'processed',
            rawResponse: ['id' => $refundId, 'status' => 'processed']
        );
    }
}
