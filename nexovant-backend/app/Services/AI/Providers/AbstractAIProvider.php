<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Models\AIModel;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Exceptions\AIAuthenticationException;
use App\Services\AI\Exceptions\AIContextOverflowException;
use App\Services\AI\Exceptions\AIProviderUnavailableException;
use App\Services\AI\Exceptions\AIRateLimitException;
use App\Services\AI\Exceptions\AIServiceException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

abstract class AbstractAIProvider implements AIProviderInterface
{
    /**
     * Cache model pricing in memory per request.
     *
     * @var array<string, AIModel>
     */
    protected array $modelCache = [];

    /**
     * Calculate cost in USD using AIModel table rates.
     */
    public function calculateCost(string $modelIdentifier, int $inputTokens, int $outputTokens): float
    {
        $model = $this->resolveModel($modelIdentifier);
        if (!$model) {
            return 0.0;
        }

        $inputCost = ((float) $model->cost_per_1k_input_tokens / 1000.0) * $inputTokens;
        $outputCost = ((float) $model->cost_per_1k_output_tokens / 1000.0) * $outputTokens;

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Resolve AIModel record.
     */
    protected function resolveModel(string $modelIdentifier): ?AIModel
    {
        if (isset($this->modelCache[$modelIdentifier])) {
            return $this->modelCache[$modelIdentifier];
        }

        $model = AIModel::where('model_identifier', $modelIdentifier)->first();
        if ($model) {
            $this->modelCache[$modelIdentifier] = $model;
        }

        return $model;
    }

    /**
     * Get HTTP client options for SSL handling across environments.
     *
     * @return array<string, mixed>
     */
    protected function getHttpOptions(): array
    {
        if (app()->isLocal() && empty(ini_get('curl.cainfo')) && empty(ini_get('openssl.cafile'))) {
            return ['verify' => false];
        }

        return [];
    }

    /**
     * Parse and map HTTP response errors to typed AI exceptions.
     */
    protected function handleHttpError(Response $response, string $modelIdentifier, ?string $fingerprint = null): void
    {
        $status = $response->status();
        $body = $response->json() ?? [];
        $rawBody = $response->body();
        $message = $this->extractErrorMessage($body, $rawBody, $status);

        if ($status === 401 || $status === 403) {
            throw new AIAuthenticationException(
                message: "Authentication failed for {$this->getName()}: {$message}",
                code: $status,
                providerSlug: $this->getSlug(),
                modelIdentifier: $modelIdentifier,
                credentialFingerprint: $fingerprint,
                context: ['response_body' => $body]
            );
        }

        if ($status === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?? 60);
            if ($retryAfter <= 0) {
                $retryAfter = 60;
            }

            throw new AIRateLimitException(
                message: "Rate limit exceeded for {$this->getName()}: {$message}",
                code: 429,
                providerSlug: $this->getSlug(),
                modelIdentifier: $modelIdentifier,
                credentialFingerprint: $fingerprint,
                context: ['response_body' => $body],
                retryAfterSeconds: $retryAfter
            );
        }

        if ($status === 400 && (str_contains(strtolower($message), 'context') || str_contains(strtolower($message), 'token limit') || str_contains(strtolower($message), 'maximum context'))) {
            throw new AIContextOverflowException(
                message: "Context window exceeded for {$modelIdentifier}: {$message}",
                code: 400,
                providerSlug: $this->getSlug(),
                modelIdentifier: $modelIdentifier,
                credentialFingerprint: $fingerprint,
                context: ['response_body' => $body]
            );
        }

        if (in_array($status, [500, 502, 503, 504], true)) {
            throw new AIProviderUnavailableException(
                message: "{$this->getName()} service unavailable (HTTP {$status}): {$message}",
                code: $status,
                providerSlug: $this->getSlug(),
                modelIdentifier: $modelIdentifier,
                credentialFingerprint: $fingerprint,
                context: ['response_body' => $body]
            );
        }

        throw new AIServiceException(
            message: "{$this->getName()} request failed with HTTP {$status}: {$message}",
            code: $status,
            providerSlug: $this->getSlug(),
            modelIdentifier: $modelIdentifier,
            credentialFingerprint: $fingerprint,
            context: ['response_body' => $body]
        );
    }

    /**
     * Extract human-readable error message from response.
     */
    abstract protected function extractErrorMessage(array $body, string $rawBody, int $status): string;
}
