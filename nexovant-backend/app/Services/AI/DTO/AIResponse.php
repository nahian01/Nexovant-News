<?php

declare(strict_types=1);

namespace App\Services\AI\DTO;

class AIResponse
{
    /**
     * @param string $content Raw text output
     * @param array<string, mixed>|null $structuredData Parsed structured JSON if requested
     * @param int $inputTokens
     * @param int $outputTokens
     * @param int $totalTokens
     * @param float $estimatedCostUsd
     * @param int $latencyMs
     * @param string $providerSlug
     * @param string $modelIdentifier
     * @param string|null $credentialId
     * @param string|null $credentialFingerprint
     * @param string $finishReason
     * @param array<string, mixed>|null $rawResponse
     */
    public function __construct(
        public string $content,
        public ?array $structuredData = null,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $totalTokens = 0,
        public float $estimatedCostUsd = 0.0,
        public int $latencyMs = 0,
        public string $providerSlug = '',
        public string $modelIdentifier = '',
        public ?string $credentialId = null,
        public ?string $credentialFingerprint = null,
        public string $finishReason = 'stop',
        public ?array $rawResponse = null
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->finishReason !== 'error' && (!empty($this->content) || !empty($this->structuredData));
    }

    /**
     * Convert to array for API serialization or logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'structured_data' => $this->structuredData,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens' => $this->totalTokens,
            'estimated_cost_usd' => $this->estimatedCostUsd,
            'latency_ms' => $this->latencyMs,
            'provider_slug' => $this->providerSlug,
            'model_identifier' => $this->modelIdentifier,
            'credential_fingerprint' => $this->credentialFingerprint,
            'finish_reason' => $this->finishReason,
        ];
    }
}
