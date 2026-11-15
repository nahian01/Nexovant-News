<?php

declare(strict_types=1);

namespace App\Services\AI\Exceptions;

use Exception;
use Throwable;

class AIServiceException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        protected ?string $providerSlug = null,
        protected ?string $modelIdentifier = null,
        protected ?string $credentialFingerprint = null,
        protected array $context = []
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getProviderSlug(): ?string
    {
        return $this->providerSlug;
    }

    public function getModelIdentifier(): ?string
    {
        return $this->modelIdentifier;
    }

    public function getCredentialFingerprint(): ?string
    {
        return $this->credentialFingerprint;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function isRetryable(): bool
    {
        return false;
    }
}
