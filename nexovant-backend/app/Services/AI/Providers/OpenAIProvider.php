<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Services\AI\DTO\AIRequest;
use App\Services\AI\DTO\AIResponse;
use App\Services\AI\DTO\ConnectionTestResult;
use App\Services\AI\DTO\EmbeddingResponse;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAIProvider extends AbstractAIProvider
{
    protected string $baseUrl = 'https://api.openai.com/v1';

    public function getName(): string
    {
        return 'OpenAI';
    }

    public function getSlug(): string
    {
        return 'openai';
    }

    public function generateText(AIRequest $request, string $apiKey, string $modelIdentifier): AIResponse
    {
        return $this->executeChat($request, $apiKey, $modelIdentifier, null);
    }

    public function generateStructured(AIRequest $request, string $apiKey, string $modelIdentifier, array $jsonSchema): AIResponse
    {
        return $this->executeChat($request, $apiKey, $modelIdentifier, $jsonSchema);
    }

    protected function executeChat(AIRequest $request, string $apiKey, string $modelIdentifier, ?array $jsonSchema): AIResponse
    {
        $startTime = hrtime(true);
        $url = "{$this->baseUrl}/chat/completions";

        $messages = [];
        if ($request->systemPrompt) {
            $messages[] = [
                'role' => 'system',
                'content' => $request->systemPrompt,
            ];
        }

        foreach ($request->messages as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        $payload = [
            'model' => $modelIdentifier,
            'messages' => $messages,
        ];

        if ($request->temperature !== null) {
            $payload['temperature'] = (float) $request->temperature;
        }

        if ($request->maxTokens !== null) {
            $payload['max_tokens'] = (int) $request->maxTokens;
        }

        if ($jsonSchema !== null) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'response_schema',
                    'schema' => $jsonSchema,
                    'strict' => false,
                ],
            ];
        }

        $timeout = $request->timeout ?? 30;

        try {
            $response = Http::withOptions($this->getHttpOptions())->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
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
        $choices = $data['choices'] ?? [];
        $rawText = $choices[0]['message']['content'] ?? '';
        $finishReason = $choices[0]['finish_reason'] ?? 'stop';

        $usage = $data['usage'] ?? [];
        $promptTokens = (int) ($usage['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($usage['completion_tokens'] ?? 0);
        $totalTokens = (int) ($usage['total_tokens'] ?? ($promptTokens + $completionTokens));

        $parsedStructured = null;
        if ($jsonSchema !== null && !empty($rawText)) {
            $decoded = json_decode($rawText, true);
            if (is_array($decoded)) {
                $parsedStructured = $decoded;
            }
        }

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
        $startTime = hrtime(true);
        $url = "{$this->baseUrl}/embeddings";

        $payload = [
            'model' => $modelIdentifier,
            'input' => $input,
        ];

        try {
            $response = Http::withOptions($this->getHttpOptions())->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(20)->post($url, $payload);
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
        $embeddings = [];
        if (!empty($data['data'])) {
            if (is_array($input) && count($input) > 1) {
                foreach ($data['data'] as $item) {
                    $embeddings[] = $item['embedding'];
                }
            } else {
                $embeddings = $data['data'][0]['embedding'] ?? [];
            }
        }

        $promptTokens = (int) ($data['usage']['prompt_tokens'] ?? 0);
        $cost = $this->calculateCost($modelIdentifier, $promptTokens, 0);

        return new EmbeddingResponse(
            embeddings: $embeddings,
            inputTokens: $promptTokens,
            estimatedCostUsd: $cost,
            latencyMs: $latencyMs,
            providerSlug: $this->getSlug(),
            modelIdentifier: $modelIdentifier
        );
    }

    public function testConnection(string $apiKey, ?string $modelIdentifier = null): ConnectionTestResult
    {
        $startTime = hrtime(true);
        $model = $modelIdentifier ?? 'gpt-4o-mini';
        $url = "{$this->baseUrl}/models/{$model}";

        try {
            $response = Http::withOptions($this->getHttpOptions())->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])->timeout(10)->get($url);

            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);

            if ($response->successful()) {
                $data = $response->json();
                return new ConnectionTestResult(
                    success: true,
                    latencyMs: $latencyMs,
                    statusCode: $response->status(),
                    message: "OpenAI connection verified. Model {$model} is active.",
                    modelVerified: $model,
                    details: ['id' => $data['id'] ?? $model]
                );
            }

            $body = $response->json() ?? [];
            $errMsg = $this->extractErrorMessage($body, $response->body(), $response->status());

            return new ConnectionTestResult(
                success: false,
                latencyMs: $latencyMs,
                statusCode: $response->status(),
                message: "OpenAI test failed: {$errMsg}",
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
