<?php

declare(strict_types=1);

namespace App\Services\AI\DTO;

class ConnectionTestResult
{
    /**
     * @param bool $success
     * @param int $latencyMs
     * @param int $statusCode
     * @param string $message
     * @param string|null $modelVerified
     * @param array<string, mixed>|null $details
     */
    public function __construct(
        public bool $success,
        public int $latencyMs,
        public int $statusCode,
        public string $message,
        public ?string $modelVerified = null,
        public ?array $details = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'latency_ms' => $this->latencyMs,
            'status_code' => $this->statusCode,
            'message' => $this->message,
            'model_verified' => $this->modelVerified,
            'details' => $this->details,
        ];
    }
}
