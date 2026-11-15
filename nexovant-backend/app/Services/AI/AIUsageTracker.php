<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AIRoutingLog;
use App\Services\AI\DTO\AIResponse;

class AIUsageTracker
{
    /**
     * Record a successful AI execution.
     *
     * @param array<string, mixed> $context
     */
    public function logSuccess(
        AIResponse $response,
        string $taskType,
        string $providerId,
        string $modelId,
        ?string $credentialId = null,
        ?string $userId = null,
        ?string $module = null,
        ?string $fallbackFromProviderId = null,
        ?string $fallbackReason = null,
        int $retryCount = 0
    ): AIRoutingLog {
        return AIRoutingLog::create([
            'task_type' => $taskType,
            'selected_provider_id' => $providerId,
            'selected_model_id' => $modelId,
            'credential_id' => $credentialId,
            'user_id' => $userId,
            'module' => $module ?? 'ai_router',
            'fallback_from_provider_id' => $fallbackFromProviderId,
            'fallback_reason' => $fallbackReason,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'total_tokens' => $response->totalTokens,
            'estimated_cost_usd' => $response->estimatedCostUsd,
            'currency' => 'USD',
            'latency_ms' => $response->latencyMs,
            'retry_count' => $retryCount,
            'fallback_attempted' => $fallbackFromProviderId !== null,
            'status' => $fallbackFromProviderId ? 'fallback_triggered' : 'success',
            'error_details' => null,
            'created_at' => now(),
        ]);
    }

    /**
     * Record a failed AI execution.
     */
    public function logFailure(
        string $taskType,
        string $providerId,
        ?string $modelId,
        ?string $credentialId,
        string $errorMessage,
        int $latencyMs = 0,
        ?string $userId = null,
        ?string $module = null,
        int $retryCount = 0
    ): AIRoutingLog {
        $resolvedModelId = $modelId ?: \App\Models\AIModel::where('provider_id', $providerId)->value('id') ?: \App\Models\AIModel::first()?->id;

        return AIRoutingLog::create([
            'task_type' => $taskType,
            'selected_provider_id' => $providerId,
            'selected_model_id' => $resolvedModelId,
            'credential_id' => $credentialId,
            'user_id' => $userId,
            'module' => $module ?? 'ai_router',
            'fallback_from_provider_id' => null,
            'fallback_reason' => null,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0,
            'estimated_cost_usd' => 0.0,
            'currency' => 'USD',
            'latency_ms' => $latencyMs,
            'retry_count' => $retryCount,
            'fallback_attempted' => false,
            'status' => 'failed',
            'error_details' => ['message' => $errorMessage],
            'created_at' => now(),
        ]);
    }
}
