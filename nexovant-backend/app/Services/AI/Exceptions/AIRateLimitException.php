<?php

declare(strict_types=1);

namespace App\Services\AI\Exceptions;

use Throwable;

class AIRateLimitException extends AIServiceException
{
    public function __construct(
        string $message = 'Rate limit exceeded for AI provider.',
        int $code = 429,
        ?Throwable $previous = null,
        ?string $providerSlug = null,
        ?string $modelIdentifier = null,
        ?string $credentialFingerprint = null,
        array $context = [],
        protected int $retryAfterSeconds = 60
    ) {
        parent::__construct($message, $code, $previous, $providerSlug, $modelIdentifier, $credentialFingerprint, $context);
    }

    public function getRetryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
