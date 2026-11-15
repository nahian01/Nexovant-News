<?php

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Models\Payment;
use App\Services\Billing\DTO\PaymentInitiationResult;
use App\Services\Billing\DTO\PaymentIntentDTO;
use App\Services\Billing\DTO\PaymentVerificationResult;
use App\Services\Billing\DTO\RefundResult;
use App\Services\Billing\DTO\WebhookResult;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    public function getGatewayKey(): string;

    public function getDisplayName(): string;

    public function isAvailableFor(string $country, string $currency): bool;

    public function initiatePayment(PaymentIntentDTO $intent): PaymentInitiationResult;

    public function verifyPayment(string $transactionReference, array $payload = []): PaymentVerificationResult;

    public function processWebhook(Request $request): WebhookResult;

    public function processRefund(Payment $payment, float $amount, string $reason): RefundResult;

    public function isConfigured(): bool;

    public function isMerchantVerified(): bool;

    public function getUnavailabilityReason(): ?string;
}
