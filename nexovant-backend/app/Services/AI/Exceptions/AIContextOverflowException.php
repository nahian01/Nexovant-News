<?php

declare(strict_types=1);

namespace App\Services\AI\Exceptions;

class AIContextOverflowException extends AIServiceException
{
    public function isRetryable(): bool
    {
        return false;
    }
}
