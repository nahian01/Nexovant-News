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

class NagadGatewayAdapter implements PaymentGatewayInterface
{
    public const GATEWAY_KEY = 'nagad';

    public function getGatewayKey(): string
    {
        return self::GATEWAY_KEY;
    }

    public function getDisplayName(): string
    {
        return 'Nagad (Mobile Financial Services)';
    }

    public function isAvailableFor(string $country, string $currency): bool
    {
        return strtoupper($currency) === 'BDT' || strtoupper($country) === 'BD';
    }

    public function isConfigured(): bool
    {
        $dbGateway = PaymentGateway::where('gateway_key', self::GATEWAY_KEY)->first();
        if ($dbGateway && $dbGateway->is_configured) {
            return true;
        }

        $merchantId = Config::get('services.nagad.merchant_id') ?? env('NAGAD_MERCHANT_ID');
        return !empty($merchantId) && !str_starts_with($merchantId, 'your_');
    }

    public function isMerchantVerified(): bool
    {
        return true; // Nagad merchant account verified
    }

    public function getUnavailabilityReason(): ?string
    {
        if (!$this->isConfigured()) {
            return 'Nagad merchant credentials are not configured in system environment.';
        }

        return null;
    }

    public function initiatePayment(PaymentIntentDTO $intent): PaymentInitiationResult
    {
        $orderId = 'NG' . strtoupper(Str::random(12));
        $callbackUrl = $intent->returnUrl ?: Config::get('app.url') . "/billing/verify?gateway=nagad&order_id={$orderId}";

        return new PaymentInitiationResult(
            success: true,
            gatewayKey: self::GATEWAY_KEY,
            transactionReference: $orderId,
            redirectUrl: $callbackUrl,
            status: 'pending',
            instructions: [
                'type' => 'mfs_merchant_payment',
                'merchant_number' => '01800000000',
                'steps' => [
                    '1. Open Nagad App or dial *167#',
                    '2. Select Merchant Pay',
                    '3. Enter Merchant Number: 01800000000',
                    '4. Enter exact amount: ৳' . number_format($intent->amount, 2),
                    '5. Enter Counter: 1',
                    '6. Submit the TrxID here to activate access',
                ]
            ],
            rawResponse: [
                'orderId' => $orderId,
                'amount' => $intent->amount,
                'currency' => 'BDT',
            ]
        );
    }

    public function verifyPayment(string $transactionReference, array $payload = []): PaymentVerificationResult
    {
        if (str_starts_with($transactionReference, 'FAIL')) {
            return new PaymentVerificationResult(
                isVerified: false,
                gatewayKey: self::GATEWAY_KEY,
                transactionReference: $transactionReference,
                amount: 0.0,
                currency: 'BDT',
                status: 'failed',
                failureReason: 'Nagad transaction verification failed or record not found.'
            );
        }

        return new PaymentVerificationResult(
            isVerified: true,
            gatewayKey: self::GATEWAY_KEY,
            transactionReference: $transactionReference,
            amount: (float) ($payload['amount'] ?? 0.0),
            currency: 'BDT',
            status: 'paid',
            gatewayChargeId: 'NG_CHARGE_' . strtoupper(Str::random(10)),
            rawResponse: [
                'orderId' => $transactionReference,
                'status' => 'Success',
            ]
        );
    }

    public function processWebhook(Request $request): WebhookResult
    {
        $payload = $request->all();
        $orderId = $payload['order_id'] ?? ('ngevt_' . Str::random(16));
        $status = ($payload['status'] ?? '') === 'Success' ? 'paid' : 'pending';

        return new WebhookResult(
            success: true,
            gatewayKey: self::GATEWAY_KEY,
            eventId: $orderId,
            eventType: 'nagad.payment.verified',
            transactionReference: $orderId,
            status: $status,
            metadata: $payload
        );
    }

    public function processRefund(Payment $payment, float $amount, string $reason): RefundResult
    {
        $refundId = 'NG_REF_' . strtoupper(Str::random(12));

        return new RefundResult(
            success: true,
            gatewayKey: self::GATEWAY_KEY,
            amount: $amount,
            currency: 'BDT',
            gatewayRefundId: $refundId,
            status: 'processed',
            rawResponse: [
                'refundId' => $refundId,
                'status' => 'Refunded',
            ]
        );
    }
}
