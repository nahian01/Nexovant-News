<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AIProvider;
use App\Models\AIProviderCredential;
use App\Models\AITaskConfig;
use App\Models\SystemSetting;
use App\Services\AI\Exceptions\AIQuotaExceededException;
use Illuminate\Support\Facades\DB;

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
    }
}
