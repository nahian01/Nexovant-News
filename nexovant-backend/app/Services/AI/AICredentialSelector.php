<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AIModel;
use App\Models\AIProvider;
use App\Models\AIProviderCredential;
use App\Services\AI\Exceptions\AIProviderUnavailableException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AICredentialSelector
{
    public function __construct(
        protected AICredentialVault $vault
    ) {
    }

    /**
     * Select best credential for a provider using the chosen strategy.
     *
     * @param AIProvider $provider
     * @param string|null $modelIdentifier
     * @param string|null $strategy Override strategy: priority, round_robin, least_utilized, lowest_cost, health, capability
     * @param array<string, bool> $requiredCapabilities e.g. ['structured' => true, 'vision' => true, 'embedding' => true]
     * @return AIProviderCredential
     */
    public function selectCredential(
        AIProvider $provider,
        ?string $modelIdentifier = null,
        ?string $strategy = null,
        array $requiredCapabilities = []
    ): AIProviderCredential {
        $eligible = $this->vault->getEligibleCredentials($provider->id, $modelIdentifier);

        if ($eligible->isEmpty()) {
            throw new AIProviderUnavailableException(
                message: "No eligible active credentials available for provider '{$provider->name}'. All keys may be exhausted, cooling down, or budget exceeded.",
                code: 503,
                providerSlug: $provider->slug,
                modelIdentifier: $modelIdentifier
            );
        }

        $chosenStrategy = $strategy ?? $provider->default_routing_strategy ?? 'priority';

        return match ($chosenStrategy) {
            'round_robin' => $this->selectRoundRobin($provider->id, $eligible),
            'least_utilized' => $this->selectLeastUtilized($eligible),
            'lowest_cost' => $this->selectLowestCost($eligible),
            'health' => $this->selectHealthBased($eligible),
            'capability' => $this->selectCapabilityBased($eligible, $provider, $requiredCapabilities),
            default => $this->selectPriority($eligible),
        };
    }

    /**
     * Strategy 1: Priority order (lowest priority number first, ties broken by least requests).
     *
     * @param Collection<int, AIProviderCredential> $credentials
     */
    protected function selectPriority(Collection $credentials): AIProviderCredential
    {
        return $credentials->sortBy([
            ['priority', 'asc'],
            ['current_daily_requests', 'asc'],
        ])->first();
    }

    /**
     * Strategy 2: Round Robin cycle evenly across credentials.
     *
     * @param Collection<int, AIProviderCredential> $credentials
     */
    protected function selectRoundRobin(string $providerId, Collection $credentials): AIProviderCredential
    {
        $count = $credentials->count();
        if ($count <= 1) {
            return $credentials->first();
        }

        $cacheKey = "ai_router_rr_{$providerId}";
        $idx = (int) Cache::increment($cacheKey);
        $offset = $idx % $count;

        return $credentials->values()->get($offset) ?? $credentials->first();
    }

    /**
     * Strategy 3: Least Utilized (lowest ratio of requests to budget, or fewest requests).
     *
     * @param Collection<int, AIProviderCredential> $credentials
     */
    protected function selectLeastUtilized(Collection $credentials): AIProviderCredential
    {
        return $credentials->sortBy(function (AIProviderCredential $cred) {
            if ($cred->daily_request_budget && $cred->daily_request_budget > 0) {
                return (float) $cred->current_daily_requests / (float) $cred->daily_request_budget;
            }

            return (float) $cred->current_daily_requests;
        })->first();
    }

    /**
     * Strategy 4: Lowest Cost (prioritizes credentials with lowest current monthly spend).
     *
     * @param Collection<int, AIProviderCredential> $credentials
     */
    protected function selectLowestCost(Collection $credentials): AIProviderCredential
    {
        return $credentials->sortBy([
            ['current_monthly_spend_usd', 'asc'],
            ['priority', 'asc'],
        ])->first();
    }

    /**
     * Strategy 5: Health Based (lowest error count, highest uptime).
     *
     * @param Collection<int, AIProviderCredential> $credentials
     */
    protected function selectHealthBased(Collection $credentials): AIProviderCredential
    {
        return $credentials->sortBy(function (AIProviderCredential $cred) {
            $errorPenalty = $cred->error_count * 1000;
            $failedRecentlyPenalty = ($cred->last_failed_request_at && $cred->last_failed_request_at->diffInMinutes() < 10) ? 500 : 0;
            return $errorPenalty + $failedRecentlyPenalty + $cred->priority;
        })->first();
    }

    /**
     * Strategy 6: Capability Based (verifies model capabilities and returns best matching credential).
     *
     * @param Collection<int, AIProviderCredential> $credentials
     * @param array<string, bool> $capabilities
     */
    protected function selectCapabilityBased(Collection $credentials, AIProvider $provider, array $capabilities): AIProviderCredential
    {
        // Query models for this provider matching capabilities
        $query = AIModel::where('provider_id', $provider->id)->where('is_active', true);

        if (!empty($capabilities['structured'])) {
            $query->where('supports_structured_output', true);
        }
        if (!empty($capabilities['vision'])) {
            $query->where('supports_vision', true);
        }
        if (!empty($capabilities['embedding'])) {
            $query->where('supports_embedding', true);
        }

        $validModelIdentifiers = $query->pluck('model_identifier')->toArray();

        if (!empty($validModelIdentifiers)) {
            // Filter credentials that allow any of these valid models
            $filtered = $credentials->filter(function (AIProviderCredential $cred) use ($validModelIdentifiers) {
                if (empty($cred->allowed_models)) {
                    return true;
                }

                return !empty(array_intersect($validModelIdentifiers, $cred->allowed_models));
            });

            if ($filtered->isNotEmpty()) {
                return $this->selectPriority($filtered);
            }
        }

        return $this->selectPriority($credentials);
    }
}
