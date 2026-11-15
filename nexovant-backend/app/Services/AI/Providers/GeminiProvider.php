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

class GeminiProvider extends AbstractAIProvider
{
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function getName(): string
    {
        return 'Google Gemini';
    }

    public function getSlug(): string
    {
        return 'google-gemini';
    }

    public function generateText(AIRequest $request, string $apiKey, string $modelIdentifier): AIResponse
    {
        return $this->executeGenerate($request, $apiKey, $modelIdentifier, null);
    }

    public function generateStructured(AIRequest $request, string $apiKey, string $modelIdentifier, array $jsonSchema): AIResponse
    {
        return $this->executeGenerate($request, $apiKey, $modelIdentifier, $jsonSchema);
    }

    protected function executeGenerate(AIRequest $request, string $apiKey, string $modelIdentifier, ?array $jsonSchema): AIResponse
    {
        $startTime = hrtime(true);
        $url = "{$this->baseUrl}/models/{$modelIdentifier}:generateContent?key={$apiKey}";

        // Map messages to Gemini contents format
        $contents = [];
        foreach ($request->messages as $msg) {
            $role = ($msg['role'] === 'assistant' || $msg['role'] === 'model') ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [
                    ['text' => $msg['content']],
                ],
            ];
        }

        $payload = [
            'contents' => $contents,
        ];

        if ($request->systemPrompt) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $request->systemPrompt],
                ],
            ];
        }

        $generationConfig = [];
        if ($request->temperature !== null) {
            $generationConfig['temperature'] = (float) $request->temperature;
        }
        if ($request->maxTokens !== null) {
            $generationConfig['maxOutputTokens'] = (int) $request->maxTokens;
        }

        if ($jsonSchema !== null) {
            $generationConfig['responseMimeType'] = 'application/json';
            $generationConfig['responseSchema'] = $jsonSchema;
        }

        if (!empty($generationConfig)) {
            $payload['generationConfig'] = $generationConfig;
        }

        $timeout = $request->timeout ?? 30;

        try {
            $response = Http::withOptions($this->getHttpOptions())->withHeaders([
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
        $candidates = $data['candidates'] ?? [];
        $rawText = '';
        if (!empty($candidates[0]['content']['parts'][0]['text'])) {
            $rawText = (string) $candidates[0]['content']['parts'][0]['text'];
        }

        $finishReason = $candidates[0]['finishReason'] ?? 'STOP';

        $usage = $data['usageMetadata'] ?? [];
        $promptTokens = (int) ($usage['promptTokenCount'] ?? 0);
        $completionTokens = (int) ($usage['candidatesTokenCount'] ?? 0);
        $totalTokens = (int) ($usage['totalTokenCount'] ?? ($promptTokens + $completionTokens));

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
            finishReason: strtolower($finishReason),
            rawResponse: $data
        );
    }

    public function generateEmbedding(string|array $input, string $apiKey, string $modelIdentifier): EmbeddingResponse
    {
        $startTime = hrtime(true);
        $url = "{$this->baseUrl}/models/{$modelIdentifier}:embedContent?key={$apiKey}";

        $text = is_array($input) ? implode("\n", $input) : (string) $input;

        $payload = [
            'model' => "models/{$modelIdentifier}",
            'content' => [
                'parts' => [
                    ['text' => $text],
                ],
            ],
        ];

        try {
            $response = Http::withOptions($this->getHttpOptions())->withHeaders([
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
        $values = $data['embedding']['values'] ?? [];

        // Estimate tokens: roughly 4 chars per token for text
        $estimatedTokens = (int) ceil(mb_strlen($text) / 4);
        $cost = $this->calculateCost($modelIdentifier, $estimatedTokens, 0);

        return new EmbeddingResponse(
            embeddings: $values,
            inputTokens: $estimatedTokens,
            estimatedCostUsd: $cost,
            latencyMs: $latencyMs,
            providerSlug: $this->getSlug(),
            modelIdentifier: $modelIdentifier
        );
    }

    public function testConnection(string $apiKey, ?string $modelIdentifier = null): ConnectionTestResult
    {
        $startTime = hrtime(true);
        $model = $modelIdentifier ?? 'gemini-1.5-flash';
        $url = "{$this->baseUrl}/models/{$model}?key={$apiKey}";

        try {
            $response = Http::withOptions($this->getHttpOptions())->timeout(10)->get($url);
            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);

            if ($response->successful()) {
                $data = $response->json();
                return new ConnectionTestResult(
                    success: true,
                    latencyMs: $latencyMs,
                    statusCode: $response->status(),
                    message: "Google Gemini connection verified. Model {$model} is active.",
                    modelVerified: $model,
                    details: ['displayName' => $data['displayName'] ?? $model]
                );
            }

            $body = $response->json() ?? [];
            $errMsg = $this->extractErrorMessage($body, $response->body(), $response->status());

            return new ConnectionTestResult(
                success: false,
                latencyMs: $latencyMs,
                statusCode: $response->status(),
                message: "Google Gemini test failed: {$errMsg}",
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
