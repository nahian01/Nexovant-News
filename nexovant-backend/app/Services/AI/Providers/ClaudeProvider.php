<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Services\AI\DTO\AIRequest;
use App\Services\AI\DTO\AIResponse;
use App\Services\AI\DTO\ConnectionTestResult;
use App\Services\AI\DTO\EmbeddingResponse;
use App\Services\AI\Exceptions\AIServiceException;
use Illuminate\Support\Facades\Http;
use Throwable;

class ClaudeProvider extends AbstractAIProvider
{
    protected string $baseUrl = 'https://api.anthropic.com/v1';

    public function getName(): string
    {
        return 'Anthropic Claude';
    }

    public function getSlug(): string
    {
        return 'anthropic';
    }

    public function generateText(AIRequest $request, string $apiKey, string $modelIdentifier): AIResponse
    {
        return $this->executeMessages($request, $apiKey, $modelIdentifier, null);
    }

    public function generateStructured(AIRequest $request, string $apiKey, string $modelIdentifier, array $jsonSchema): AIResponse
    {
        return $this->executeMessages($request, $apiKey, $modelIdentifier, $jsonSchema);
    }

    protected function executeMessages(AIRequest $request, string $apiKey, string $modelIdentifier, ?array $jsonSchema): AIResponse
    {
        $startTime = hrtime(true);
        $url = "{$this->baseUrl}/messages";

        $messages = [];
        foreach ($request->messages as $msg) {
            $role = ($msg['role'] === 'system') ? 'user' : $msg['role'];
            $messages[] = [
                'role' => $role,
                'content' => $msg['content'],
            ];
        }

        // Claude requires max_tokens
        $maxTokens = $request->maxTokens ?? 4096;

        $payload = [
            'model' => $modelIdentifier,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
        ];

        if ($request->systemPrompt) {
            $payload['system'] = $request->systemPrompt;
        }

        if ($request->temperature !== null) {
            $payload['temperature'] = (float) $request->temperature;
        }

        if ($jsonSchema !== null) {
            $payload['tools'] = [
                [
                    'name' => 'extract_structured_data',
                    'description' => 'Extract structured data strictly matching the provided JSON schema',
                    'input_schema' => $jsonSchema,
                ],
            ];
            $payload['tool_choice'] = [
                'type' => 'tool',
                'name' => 'extract_structured_data',
            ];
        }

        $timeout = $request->timeout ?? 30;

        try {
            $response = Http::withOptions($this->getHttpOptions())->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->timeout($timeout)->post($url, $payload);
        } catch (Throwable $e) {
            $this->handleHttpError(
                new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(504, [], json_encode(['error' => ['message' => $e->getMessage()]]))),
                $modelIdentifier
            );
        }

        $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);

        if (!$response->successful()) {
            $this->handleHttpError($response, $modelIdentifier);
        }

        $data = $response->json() ?? [];
        $contentBlocks = $data['content'] ?? [];

        $rawText = '';
        $parsedStructured = null;

        foreach ($contentBlocks as $block) {
            if ($block['type'] === 'text') {
                $rawText .= $block['text'] ?? '';
            } elseif ($block['type'] === 'tool_use' && $block['name'] === 'extract_structured_data') {
                $parsedStructured = $block['input'] ?? null;
                if (empty($rawText) && $parsedStructured) {
                    $rawText = json_encode($parsedStructured, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
            }
        }

        if ($jsonSchema !== null && $parsedStructured === null && !empty($rawText)) {
            $decoded = json_decode($rawText, true);
            if (is_array($decoded)) {
                $parsedStructured = $decoded;
            }
        }

        $finishReason = $data['stop_reason'] ?? 'end_turn';
        $usage = $data['usage'] ?? [];
        $promptTokens = (int) ($usage['input_tokens'] ?? 0);
        $completionTokens = (int) ($usage['output_tokens'] ?? 0);
        $totalTokens = $promptTokens + $completionTokens;

        $cost = $this->calculateCost($modelIdentifier, $promptTokens, $completionTokens);

        return new AIResponse(
            content: $rawText,
            structuredData: $parsedStructured,
            inputTokens: $promptTokens,
            outputTokens: $completionTokens,
            totalTokens: $totalTokens,
            estimatedCostUsd: $cost,
            latencyMs: $latencyMs,
            providerSlug: $this->getSlug(),
            modelIdentifier: $modelIdentifier,
            finishReason: $finishReason,
            rawResponse: $data
        );
    }

    public function generateEmbedding(string|array $input, string $apiKey, string $modelIdentifier): EmbeddingResponse
    {
        throw new AIServiceException(
            message: "Anthropic Claude does not provide native embeddings API. Please route embedding tasks to Google Gemini or OpenAI.",
            code: 400,
            providerSlug: $this->getSlug(),
            modelIdentifier: $modelIdentifier
        );
    }

    public function testConnection(string $apiKey, ?string $modelIdentifier = null): ConnectionTestResult
    {
        $startTime = hrtime(true);
        $model = $modelIdentifier ?? 'claude-3-5-haiku-20241022';
        $url = "{$this->baseUrl}/models";

        try {
            $response = Http::withOptions($this->getHttpOptions())->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(10)->get($url);

            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);

            if ($response->successful()) {
                return new ConnectionTestResult(
                    success: true,
                    latencyMs: $latencyMs,
                    statusCode: $response->status(),
                    message: "Anthropic Claude connection verified successfully.",
                    modelVerified: $model,
                    details: ['status' => 'active']
                );
            }

            $body = $response->json() ?? [];
            $errMsg = $this->extractErrorMessage($body, $response->body(), $response->status());

            return new ConnectionTestResult(
                success: false,
                latencyMs: $latencyMs,
                statusCode: $response->status(),
                message: "Anthropic Claude test failed: {$errMsg}",
                modelVerified: $model,
                details: $body
            );
        } catch (Throwable $e) {
            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);
            return new ConnectionTestResult(
                success: false,
                latencyMs: $latencyMs,
                statusCode: 0,
                message: "Connection error: {$e->getMessage()}",
                modelVerified: $model
            );
        }
    }

    protected function extractErrorMessage(array $body, string $rawBody, int $status): string
    {
        return $body['error']['message'] ?? ($body['message'] ?? "HTTP {$status}: " . substr($rawBody, 0, 200));
    }
}
