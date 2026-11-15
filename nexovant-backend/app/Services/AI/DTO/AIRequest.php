<?php

declare(strict_types=1);

namespace App\Services\AI\DTO;

class AIRequest
{
    /**
     * @param string $taskType e.g. 'article_summarization', 'fact_extraction'
     * @param array<int, array{role: string, content: string}> $messages
     * @param string|null $systemPrompt
     * @param array<string, mixed>|null $jsonSchema
     * @param float|null $temperature
     * @param int|null $maxTokens
     * @param int|null $timeout
     * @param array<string, mixed> $options
     * @param string|null $userId
     * @param string|null $module
     */
    public function __construct(
        public string $taskType,
        public array $messages = [],
        public ?string $systemPrompt = null,
        public ?array $jsonSchema = null,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ?int $timeout = null,
        public array $options = [],
        public ?string $userId = null,
        public ?string $module = null
    ) {
    }

    /**
     * Helper to create a request from a simple prompt.
     */
    public static function fromPrompt(
        string $taskType,
        string $prompt,
        ?string $systemPrompt = null,
        array $options = []
    ): self {
        return new self(
            taskType: $taskType,
            messages: [
                ['role' => 'user', 'content' => $prompt],
            ],
            systemPrompt: $systemPrompt,
            options: $options
        );
    }

    /**
     * Helper to create a structured JSON extraction request.
     */
    public static function structured(
        string $taskType,
        string $prompt,
        array $jsonSchema,
        ?string $systemPrompt = null,
        array $options = []
    ): self {
        return new self(
            taskType: $taskType,
            messages: [
                ['role' => 'user', 'content' => $prompt],
            ],
            systemPrompt: $systemPrompt,
            jsonSchema: $jsonSchema,
            options: $options
        );
    }
}
