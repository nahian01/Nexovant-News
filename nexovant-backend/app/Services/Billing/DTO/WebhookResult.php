<?php

declare(strict_types=1);

namespace App\Services\Billing\DTO;

class WebhookResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $gatewayKey,
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly bool $isDuplicate = false,
        public readonly ?string $transactionReference = null,
        public readonly ?string $status = null,
        public readonly ?string $errorMessage = null,
        public readonly array $metadata = []
    ) {}
}
