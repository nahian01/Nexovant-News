<?php

declare(strict_types=1);

namespace App\Services\AI\Exceptions;

class AIProviderUnavailableException extends AIServiceException
{
    public function isRetryable(): bool
    {
        return true;
    }
}
