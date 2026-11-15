<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

use App\Services\AI\DTO\AIRequest;
use App\Services\AI\DTO\AIResponse;
use App\Services\AI\DTO\ConnectionTestResult;
use App\Services\AI\DTO\EmbeddingResponse;

interface AIProviderInterface
{
    /**
     * Get the human-readable provider name (e.g. 'Google Gemini', 'OpenAI', 'Anthropic Claude').
     */
    public function getName(): string;

    /**
     * Get the unique provider slug ('google-gemini', 'openai', 'anthropic').
     */
    public function getSlug(): string;

    /**
     * Execute text generation.
     */
    public function generateText(AIRequest $request, string $apiKey, string $modelIdentifier): AIResponse;

    /**
     * Execute structured JSON generation adhering to provided schema.
     */
    public function generateStructured(AIRequest $request, string $apiKey, string $modelIdentifier, array $jsonSchema): AIResponse;

    /**
     * Generate text embeddings.
     *
     * @param string|array<int, string> $input
     */
    public function generateEmbedding(string|array $input, string $apiKey, string $modelIdentifier): EmbeddingResponse;

    /**
     * Test connection and authentication to provider.
     */
    public function testConnection(string $apiKey, ?string $modelIdentifier = null): ConnectionTestResult;

    /**
     * Calculate cost in USD based on input and output token counts for a given model.
     */
    public function calculateCost(string $modelIdentifier, int $inputTokens, int $outputTokens): float;
}
