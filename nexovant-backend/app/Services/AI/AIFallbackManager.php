<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AIProvider;
use App\Models\AITaskConfig;
use App\Services\AI\Exceptions\AIServiceException;
use Illuminate\Support\Collection;

class AIFallbackManager
{
    protected int $failureThreshold = 5;
    protected int $resetTimeoutSeconds = 60;

    /**
     * Check if a provider is currently healthy and eligible for requests.
     */
    public function isProviderAvailable(AIProvider $provider): bool
    {
        if (!$provider->is_active) {
            return false;
        }

        if ($provider->circuit_state === 'closed') {
            return true;
        }

        if ($provider->circuit_state === 'open') {
            // Check if reset timeout has elapsed to transition to half_open
            if ($provider->circuit_tripped_at && $provider->circuit_tripped_at->diffInSeconds(now()) >= $this->resetTimeoutSeconds) {
                $provider->update([
                    'circuit_state' => 'half_open',
                ]);
                return true;
            }

            return false;
        }

        if ($provider->circuit_state === 'half_open') {
            // In half-open, allow request through as probe
            return true;
        }

        return false;
    }

    /**
     * Record a failure on a provider. Trip circuit breaker if threshold reached.
     */
    public function recordFailure(AIProvider $provider, string $reason): void
    {
        $newFailures = $provider->consecutive_failures + 1;

        if ($newFailures >= $this->failureThreshold || $provider->circuit_state === 'half_open') {
            $provider->update([
                'circuit_state' => 'open',
                'consecutive_failures' => $newFailures,
                'circuit_tripped_at' => now(),
            ]);
        } else {
            $provider->update([
                'consecutive_failures' => $newFailures,
            ]);
        }
    }

    /**
     * Record a success on a provider. Reset circuit breaker to closed.
     */
    public function recordSuccess(AIProvider $provider): void
    {
        if ($provider->circuit_state !== 'closed' || $provider->consecutive_failures > 0) {
            $provider->update([
                'circuit_state' => 'closed',
                'consecutive_failures' => 0,
                'circuit_tripped_at' => null,
            ]);
        }
    }

    /**
     * Build the ordered list of fallback providers for a task.
     *
     * @return Collection<int, AIProvider>
     */
    public function getFallbackProviders(AITaskConfig $taskConfig, string $excludeProviderId): Collection
    {
        $fallbackIds = $taskConfig->fallback_provider_ids ?? [];
        if (empty($fallbackIds)) {
            // Default global fallback sequence if task does not specify: all other active providers ordered by priority
            return AIProvider::where('id', '!=', $excludeProviderId)
                ->where('is_active', true)
                ->orderBy('priority')
                ->get();
        }

        $providers = AIProvider::whereIn('id', $fallbackIds)
            ->where('id', '!=', $excludeProviderId)
            ->where('is_active', true)
            ->get();

        // Preserve order specified in fallback_provider_ids array
        return collect($fallbackIds)
            ->map(fn ($id) => $providers->firstWhere('id', $id))
            ->filter()
            ->values();
    }

    /**
     * Calculate bounded exponential backoff delay with jitter.
     */
    public function calculateBackoffMs(int $attempt, int $baseMs = 500, int $maxMs = 5000): int
    {
        $exponential = $baseMs * (2 ** ($attempt - 1));
        $jitter = random_int(0, (int) round($exponential * 0.2));
        return min($maxMs, $exponential + $jitter);
    }
}
