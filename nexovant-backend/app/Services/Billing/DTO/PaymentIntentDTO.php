<?php

declare(strict_types=1);

namespace App\Services\Billing\DTO;

class PaymentIntentDTO
{
    public function __construct(
        public readonly string $invoiceId,
        public readonly string $userId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $paymentMethod,
        public readonly string $country,
        public readonly ?string $customerEmail = null,
        public readonly ?string $customerName = null,
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $returnUrl = null,
        public readonly ?string $cancelUrl = null,
        public readonly array $metadata = []
    ) {}
}
