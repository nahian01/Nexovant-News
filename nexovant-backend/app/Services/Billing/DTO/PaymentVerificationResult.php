<?php

declare(strict_types=1);

namespace App\Services\Billing\DTO;

class PaymentVerificationResult
{
    public function __construct(
        public readonly bool $isVerified,
        public readonly string $gatewayKey,
        public readonly string $transactionReference,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $status, // 'paid', 'pending_review', 'failed', 'refunded'
        public readonly ?string $gatewayChargeId = null,
        public readonly ?string $failureReason = null,
        public readonly array $rawResponse = []
    ) {}
}
