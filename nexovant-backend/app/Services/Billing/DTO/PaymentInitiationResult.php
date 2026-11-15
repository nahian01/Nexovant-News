<?php

declare(strict_types=1);

namespace App\Services\Billing\DTO;

class PaymentInitiationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $gatewayKey,
        public readonly ?string $transactionReference = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $clientSecret = null,
        public readonly string $status = 'pending',
        public readonly ?string $errorMessage = null,
        public readonly array $instructions = [],
        public readonly array $rawResponse = []
    ) {}
}
