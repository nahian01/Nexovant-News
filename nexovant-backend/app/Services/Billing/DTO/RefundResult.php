<?php

declare(strict_types=1);

namespace App\Services\Billing\DTO;

class RefundResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $gatewayKey,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $gatewayRefundId = null,
        public readonly string $status = 'processed',
        public readonly ?string $errorMessage = null,
        public readonly array $rawResponse = []
    ) {}
}
