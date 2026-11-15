<?php

declare(strict_types=1);

namespace App\Services\AI\DTO;

class EmbeddingResponse
{
    /**
     * @param array<int, array<int, float>>|array<int, float> $embeddings
     * @param int $inputTokens
     * @param float $estimatedCostUsd
     * @param int $latencyMs
     * @param string $providerSlug
     * @param string $modelIdentifier
     */
    public function __construct(
        public array $embeddings,
        public int $inputTokens = 0,
        public float $estimatedCostUsd = 0.0,
        public int $latencyMs = 0,
        public string $providerSlug = '',
        public string $modelIdentifier = ''
    ) {
    }

    /**
     * Helper to get vector for single input.
     *
     * @return array<int, float>
     */
    public function getFirstVector(): array
    {
        if (isset($this->embeddings[0]) && is_array($this->embeddings[0])) {
            return $this->embeddings[0];
        }

        return $this->embeddings;
    }
}
