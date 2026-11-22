<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AIProvider;
use App\Models\AIProviderCredential;
use App\Models\AITaskConfig;
use App\Models\SystemSetting;
use App\Services\AI\Exceptions\AIQuotaExceededException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AIBudgetManager
{
    /**
     * Check all budget tiers before dispatch.
     *
     * @throws AIQuotaExceededException
     */
    public function validateBudget(
        AIProvider $provider,
        AIProviderCredential $credential,
        ?AITaskConfig $taskConfig = null,
        float $estimatedCostUsd = 0.0
    ): void {
        // Tier 1: Platform Global Budget
        $this->checkPlatformBudget($estimatedCostUsd);

        // Tier 2: Provider Monthly Budget
        if ($provider->provider_budget_monthly_usd !== null && $provider->provider_budget_monthly_usd > 0) {
            $potentialSpend = (float) $provider->current_monthly_spend_usd + $estimatedCostUsd;
            if ($potentialSpend > (float) $provider->provider_budget_monthly_usd) {
                throw new AIQuotaExceededException(
                    message: "Monthly budget of \${$provider->provider_budget_monthly_usd} for provider '{$provider->name}' exceeded. Current spend: \${$provider->current_monthly_spend_usd}.",
                    code: 402,
                    providerSlug: $provider->slug
                );
            }
        }

        // Tier 3: Credential Daily & Monthly Budgets
        if ($credential->daily_request_budget && $credential->current_daily_requests >= $credential->daily_request_budget) {
            throw new AIQuotaExceededException(
                message: "Daily request limit of {$credential->daily_request_budget} reached for credential '{$credential->display_name}'.",
                code: 402,
                providerSlug: $provider->slug,
                credentialFingerprint: $credential->key_fingerprint
            );
        }

        if ($credential->monthly_spending_budget !== null && $credential->monthly_spending_budget > 0) {
            $potentialCredSpend = (float) $credential->current_monthly_spend_usd + $estimatedCostUsd;
            if ($potentialCredSpend > (float) $credential->monthly_spending_budget) {
                throw new AIQuotaExceededException(
                    message: "Monthly spending limit of \${$credential->monthly_spending_budget} reached for credential '{$credential->display_name}'.",
                    code: 402,
                    providerSlug: $provider->slug,
                    credentialFingerprint: $credential->key_fingerprint
                );
            }
        }

        // Tier 4: Task-Level Budget
        if ($taskConfig && $taskConfig->budget_limit_usd !== null && $taskConfig->budget_limit_usd > 0) {
            if ($estimatedCostUsd > (float) $taskConfig->budget_limit_usd) {
                throw new AIQuotaExceededException(
                    message: "Estimated request cost (\${$estimatedCostUsd}) exceeds maximum allowed budget (\${$taskConfig->budget_limit_usd}) for task '{$taskConfig->task_type}'.",
                    code: 402,
                    providerSlug: $provider->slug
                );
            }
        }
    }

    /**
     * Check global platform monthly budget.
     */
    protected function checkPlatformBudget(float $estimatedCostUsd): void
    {
        try {
            $globalLimit = (float) SystemSetting::get('ai_global_monthly_budget_usd', 5000.0);
            $totalMonthSpend = (float) DB::table('ai_providers')->sum('current_monthly_spend_usd');

            if ($totalMonthSpend + $estimatedCostUsd > $globalLimit) {
                throw new AIQuotaExceededException(
                    message: "Global Nexovant platform AI monthly budget of \${$globalLimit} reached. Current spend: \${$totalMonthSpend}.",
                    code: 402
                );
            }
        } catch (AIQuotaExceededException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // If table not yet loaded in test or error, continue
        }
    }

    /**
     * Reconcile actual token consumption and cost after request completion.
     */
    public function recordConsumption(
        AIProvider $provider,
        AIProviderCredential $credential,
        int $inputTokens,
        int $outputTokens,
        float $actualCostUsd
    ): void {
        $totalTokens = $inputTokens + $outputTokens;

        DB::transaction(function () use ($provider, $credential, $totalTokens, $actualCostUsd) {
            // Update Credential
            $credential->increment('current_daily_requests');
            $credential->increment('current_daily_tokens', $totalTokens);
            $credential->increment('current_monthly_spend_usd', $actualCostUsd);
            $credential->update([
                'last_successful_request_at' => now(),
            ]);

            // Update Provider
            $provider->increment('current_monthly_spend_usd', $actualCostUsd);
        });

        // Evaluate token thresholds and auto-rotate when limit is reached
        $this->evaluateQuotaThresholds($provider, $credential->fresh() ?? $credential);
    }

    /**
     * Monitor token consumption against daily free quota, send alerts to operator, and auto-rotate.
     */
    public function evaluateQuotaThresholds(AIProvider $provider, AIProviderCredential $credential): void
    {
        $dailyBudget = $credential->daily_token_budget ?: 500000;
        $currentTokens = (int) $credential->current_daily_tokens;
        $ratio = (float) $currentTokens / (float) $dailyBudget;
        $alertRecipient = 'abdullahalnahian0@gmail.com';

        // 1. Approaching free limit warning (80% - 99%)
        if ($ratio >= 0.80 && $ratio < 1.0) {
            $cacheKey = "ai_quota_warning_{$credential->id}";
            if (!Cache::has($cacheKey)) {
                Cache::put($cacheKey, true, now()->addHours(12));
                try {
                    Mail::to($alertRecipient)->send(new \App\Mail\AIQuotaWarningMail(
                        provider: $provider,
                        credential: $credential,
                        currentTokens: $currentTokens,
                        dailyTokenBudget: $dailyBudget,
                        usagePercentage: $ratio * 100,
                        isRotated: false
                    ));
                } catch (\Throwable $e) {
                    Log::warning("Failed to dispatch AI quota warning: {$e->getMessage()}");
                }
            }
        }

        // 2. Limit reached: Mark exhausted, rotate key & notify
        if ($ratio >= 1.0) {
            $credential->update([
                'status' => 'quota_exhausted',
                'last_error_reason' => 'Daily free token limit reached (' . number_format($currentTokens) . ' tokens). Key rotated.'
            ]);

            $rotationCacheKey = "ai_quota_rotated_{$credential->id}";
            if (!Cache::has($rotationCacheKey)) {
                Cache::put($rotationCacheKey, true, now()->addHours(12));
                try {
                    Mail::to($alertRecipient)->send(new \App\Mail\AIQuotaWarningMail(
                        provider: $provider,
                        credential: $credential,
                        currentTokens: $currentTokens,
                        dailyTokenBudget: $dailyBudget,
                        usagePercentage: $ratio * 100,
                        isRotated: true
                    ));
                } catch (\Throwable $e) {
                    Log::warning("Failed to dispatch AI rotation alert: {$e->getMessage()}");
                }
            }
        }
    }
}
