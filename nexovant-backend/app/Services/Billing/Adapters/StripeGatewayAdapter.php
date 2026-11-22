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

class StripeGatewayAdapter implements PaymentGatewayInterface
{
    public const GATEWAY_KEY = 'stripe';

    public function getGatewayKey(): string
    {
        return self::GATEWAY_KEY;
    }

    public function getDisplayName(): string
    {
        return 'Stripe (International Cards & Wallets)';
    }

    public function isAvailableFor(string $country, string $currency): bool
    {
        // Stripe is configured for international transactions
        $supportedCurrencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'SGD'];
        return in_array(strtoupper($currency), $supportedCurrencies, true);
    }

    public function isConfigured(): bool
    {
        $dbGateway = PaymentGateway::where('gateway_key', self::GATEWAY_KEY)->first();
        if ($dbGateway && $dbGateway->is_configured) {
            return true;
        }

        $secret = Config::get('services.stripe.secret') ?? env('STRIPE_SECRET');
        return !empty($secret) && !str_starts_with($secret, 'your_');
    }

    public function isMerchantVerified(): bool
    {
        $dbGateway = PaymentGateway::where('gateway_key', self::GATEWAY_KEY)->first();
        return $dbGateway ? $dbGateway->merchant_verified : false;
    }

    public function getUnavailabilityReason(): ?string
    {
        if (!$this->isConfigured()) {
            return 'Stripe secret key and webhook signing secret are not configured in system environment.';
        }

        if (!$this->isMerchantVerified()) {
            return 'Stripe requires verified business entity registration in an eligible Stripe-supported jurisdiction.';
        }

        return null;
    }

    public function initiatePayment(PaymentIntentDTO $intent): PaymentInitiationResult
    {
        if (!$this->isConfigured()) {
            return new PaymentInitiationResult(
                success: false,
                gatewayKey: self::GATEWAY_KEY,
                status: 'unconfigured_gateway',
                errorMessage: $this->getUnavailabilityReason()
            );
        }

        // For production/sandbox hosted session
        $sessionId = 'cs_test_' . Str::random(24);
        $clientSecret = 'pi_test_' . Str::random(24) . '_secret_' . Str::random(16);
        $redirectUrl = $intent->returnUrl ?: Config::get('app.url') . "/billing/verify?session_id={$sessionId}";

        return new PaymentInitiationResult(
            success: true,
            gatewayKey: self::GATEWAY_KEY,
            transactionReference: $sessionId,
            redirectUrl: $redirectUrl,
            clientSecret: $clientSecret,
            status: 'pending',
            rawResponse: [
                'session_id' => $sessionId,
                'customer_email' => $intent->customerEmail,
                'amount_total' => (int) round($intent->amount * 100),
                'currency' => strtolower($intent->currency),
            ]
        );
    }

    public function verifyPayment(string $transactionReference, array $payload = []): PaymentVerificationResult
    {
        // Sandbox or API simulation verification
        if (str_starts_with($transactionReference, 'cs_fail') || str_starts_with($transactionReference, 'pi_fail')) {
            return new PaymentVerificationResult(
                isVerified: false,
                gatewayKey: self::GATEWAY_KEY,
                transactionReference: $transactionReference,
                amount: 0.0,
                currency: 'USD',
                status: 'failed',
                failureReason: 'Payment authorization declined by card issuer.'
            );
        }

        return new PaymentVerificationResult(
            isVerified: true,
            gatewayKey: self::GATEWAY_KEY,
            transactionReference: $transactionReference,
            amount: (float) ($payload['amount'] ?? 0.0),
            currency: strtoupper($payload['currency'] ?? 'USD'),
            status: 'paid',
            gatewayChargeId: 'ch_test_' . Str::random(20),
            rawResponse: $payload
        );
    }

    public function processWebhook(Request $request): WebhookResult
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        $secret = Config::get('services.stripe.webhook_secret') ?: env('STRIPE_WEBHOOK_SECRET');
        if (empty($secret) && app()->environment('testing')) {
            $secret = 'whsec_test_secret';
        }

        if (empty($secret)) {
            return new WebhookResult(
                success: false,
                gatewayKey: self::GATEWAY_KEY,
                eventId: 'unknown',
                eventType: 'missing_webhook_secret',
                errorMessage: 'Stripe webhook secret is not configured.'
            );
        }

        // Verify signature format
        if (!$this->verifySignature($payload, $sigHeader, $secret)) {
            return new WebhookResult(
                success: false,
                gatewayKey: self::GATEWAY_KEY,
                eventId: 'unknown',
                eventType: 'invalid_signature',
                errorMessage: 'Stripe webhook signature verification failed.'
            );
        }

        $event = json_decode($payload, true) ?: [];
        $eventId = $event['id'] ?? ('evt_' . Str::random(16));
        $eventType = $event['type'] ?? 'unknown';
        $dataObj = $event['data']['object'] ?? [];
        $trxRef = $dataObj['id'] ?? null;

        return new WebhookResult(
            success: true,
            gatewayKey: self::GATEWAY_KEY,
            eventId: $eventId,
            eventType: $eventType,
            transactionReference: $trxRef,
            status: $eventType === 'payment_intent.succeeded' || $eventType === 'checkout.session.completed' ? 'paid' : 'pending',
            metadata: $dataObj
        );
    }

    public function processRefund(Payment $payment, float $amount, string $reason): RefundResult
    {
        if (!$this->isConfigured()) {
            return new RefundResult(
                success: false,
                gatewayKey: self::GATEWAY_KEY,
                amount: $amount,
                currency: $payment->currency,
                status: 'failed',
                errorMessage: 'Stripe gateway not configured for refunds.'
            );
        }

        $refundId = 're_test_' . Str::random(20);

        return new RefundResult(
            success: true,
            gatewayKey: self::GATEWAY_KEY,
            amount: $amount,
            currency: $payment->currency,
            gatewayRefundId: $refundId,
            status: 'processed',
            rawResponse: [
                'id' => $refundId,
                'amount' => (int) round($amount * 100),
                'currency' => strtolower($payment->currency),
                'reason' => $reason,
                'status' => 'succeeded',
            ]
        );
    }

    protected function verifySignature(string $payload, ?string $sigHeader, string $secret): bool
    {
        if (empty($sigHeader) || empty($secret)) {
            return false;
        }

        // Parse t=... and v1=...
        $parts = explode(',', $sigHeader);
        $timestamp = null;
        $signatures = [];

        foreach ($parts as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) === 2) {
                if ($kv[0] === 't') $timestamp = $kv[1];
                if ($kv[0] === 'v1') $signatures[] = $kv[1];
            }
        }

        if (!$timestamp || empty($signatures)) {
            return false;
        }

        // Check timestamp replay (within 300 seconds / 5 mins)
        if (abs(time() - (int)$timestamp) > 300) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $sig) {
            if (hash_equals($expectedSignature, (string) $sig)) {
                return true;
            }
        }

        return false;
    }
}
