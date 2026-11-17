<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AIProviderCredential;
use App\Services\AI\Exceptions\AIAuthenticationException;
use App\Services\AI\Exceptions\AIQuotaExceededException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class AICredentialVault
{
    /**
     * Store and encrypt a new API credential.
     *
     * @param array<string, mixed> $data
     */
    public function registerCredential(array $data): AIProviderCredential
    {
        $rawKey = trim((string) $data['api_key']);
        $fingerprint = $this->calculateFingerprint($rawKey);

        $existing = AIProviderCredential::where('key_fingerprint', $fingerprint)->first();
        if ($existing) {
            throw new \InvalidArgumentException("This API credential is already registered with label '{$existing->display_name}'.");
        }

        $encrypted = Crypt::encryptString($rawKey);

        return AIProviderCredential::create([
            'provider_id' => $data['provider_id'],
            'label' => $data['display_name'] ?? 'Credential ' . substr($fingerprint, 0, 8),
            'display_name' => $data['display_name'] ?? 'Credential ' . substr($fingerprint, 0, 8),
            'encrypted_api_key' => $encrypted,
            'key_fingerprint' => $fingerprint,
            'account_label' => $data['account_label'] ?? null,
            'allowed_models' => $data['allowed_models'] ?? null,
            'priority' => (int) ($data['priority'] ?? 1),
            'routing_eligibility' => (bool) ($data['routing_eligibility'] ?? true),
            'rate_limit_config' => $data['rate_limit_config'] ?? null,
            'daily_request_budget' => isset($data['daily_request_budget']) ? (int) $data['daily_request_budget'] : null,
            'daily_token_budget' => isset($data['daily_token_budget']) ? (int) $data['daily_token_budget'] : null,
            'monthly_spending_budget' => isset($data['monthly_spending_budget']) ? (float) $data['monthly_spending_budget'] : null,
            'status' => $data['status'] ?? 'active',
            'is_valid' => true,
            'last_tested_at' => now(),
            'last_health_check_at' => now(),
        ]);
    }

    /**
     * Automatically discover and register API credentials specified in .env or configuration.
     * Supports:
     * - GEMINI_API_KEY / GOOGLE_GEMINI_API_KEY / GOOGLE_API_KEY
     * - OPENAI_API_KEY
     * - ANTHROPIC_API_KEY / CLAUDE_API_KEY
     *
     * @return array<string, AIProviderCredential>
     */
    public function syncFromEnvironment(): array
    {
        $envProviders = [
            'google-gemini' => [
                'env' => ['GEMINI_API_KEY', 'GOOGLE_GEMINI_API_KEY', 'GOOGLE_API_KEY'],
                'label' => 'Google Gemini Primary Gateway (.env)',
            ],
            'openai' => [
                'env' => ['OPENAI_API_KEY'],
                'label' => 'OpenAI GPT Primary Gateway (.env)',
            ],
            'anthropic' => [
                'env' => ['ANTHROPIC_API_KEY', 'CLAUDE_API_KEY'],
                'label' => 'Anthropic Claude Primary Gateway (.env)',
            ],
        ];

        $synced = [];

        foreach ($envProviders as $slug => $config) {
            $apiKey = null;
            foreach ($config['env'] as $varName) {
                $val = env($varName) ?: getenv($varName);
                if (!empty($val) && strlen(trim((string) $val)) >= 10) {
                    $apiKey = trim((string) $val);
                    break;
                }
            }

            if (!$apiKey) {
                continue;
            }

            $provider = \App\Models\AIProvider::where('slug', $slug)->first();
            if (!$provider) {
                continue;
            }

            // Ensure provider is active
            if (!$provider->is_active) {
                $provider->update(['is_active' => true]);
            }

            $fingerprint = $this->calculateFingerprint($apiKey);
            $existing = AIProviderCredential::where('key_fingerprint', $fingerprint)->first();

            if ($existing) {
                if (!$existing->routing_eligibility || $existing->status !== 'active') {
                    $existing->update([
                        'status' => 'active',
                        'routing_eligibility' => true,
                        'is_valid' => true,
                    ]);
                }
                $synced[$slug] = $existing;
            } else {
                $encrypted = Crypt::encryptString($apiKey);
                $cred = AIProviderCredential::create([
                    'provider_id' => $provider->id,
                    'label' => $config['label'],
                    'display_name' => $config['label'],
                    'encrypted_api_key' => $encrypted,
                    'key_fingerprint' => $fingerprint,
                    'account_label' => 'Environment (.env)',
                    'priority' => 1,
                    'routing_eligibility' => true,
                    'status' => 'active',
                    'is_valid' => true,
                    'daily_request_budget' => 5000,
                    'daily_token_budget' => 5000000,
                    'monthly_spending_budget' => 500.0,
                    'last_tested_at' => now(),
                    'last_health_check_at' => now(),
                ]);
                $synced[$slug] = $cred;
            }
        }

        return $synced;
    }

    /**
     * Decrypt credential secret for outbound dispatch strictly in memory.
     */
    public function decryptSecret(AIProviderCredential $credential): string
    {
        try {
            return Crypt::decryptString($credential->encrypted_api_key);
        } catch (Throwable $e) {
            throw new AIAuthenticationException(
                message: "Failed to decrypt secret key for credential {$credential->id}: {$e->getMessage()}",
                code: 500,
                credentialFingerprint: $credential->key_fingerprint
            );
        }
    }

    /**
     * Generate SHA-256 fingerprint for deduplication and logging.
     */
    public function calculateFingerprint(string $apiKey): string
    {
        return hash('sha256', trim($apiKey));
    }

    /**
     * Format a masked representation of an API key for safe UI display.
     */
    public function maskApiKey(string $apiKey): string
    {
        $trimmed = trim($apiKey);
        $len = strlen($trimmed);
        if ($len <= 10) {
            return 'sk-***';
        }

        return substr($trimmed, 0, 7) . '...' . substr($trimmed, -4);
    }

    /**
     * Retrieve all active and eligible credentials for a provider, automatically recovering expired cooldowns.
     *
     * @return Collection<int, AIProviderCredential>
     */
    public function getEligibleCredentials(string $providerId, ?string $modelIdentifier = null): Collection
    {
        $credentials = AIProviderCredential::where('provider_id', $providerId)
            ->where('is_valid', true)
            ->where('routing_eligibility', true)
            ->get();

        if ($credentials->isEmpty()) {
            $this->syncFromEnvironment();
            $credentials = AIProviderCredential::where('provider_id', $providerId)
                ->where('is_valid', true)
                ->where('routing_eligibility', true)
                ->get();
        }

        $eligible = collect();

        foreach ($credentials as $cred) {
            // Check cooldown expiration
            if ($cred->status === 'cooldown' || $cred->status === 'rate_limited') {
                if ($cred->cooldown_expires_at && $cred->cooldown_expires_at->isPast()) {
                    $cred->update([
                        'status' => 'active',
                        'cooldown_expires_at' => null,
                        'last_error_reason' => null,
                    ]);
                } else {
                    continue; // Still in cooldown
                }
            }

            if ($cred->status !== 'active') {
                continue;
            }

            // Check model whitelist if restricted
            if (!empty($cred->allowed_models) && $modelIdentifier !== null) {
                if (!in_array($modelIdentifier, $cred->allowed_models, true)) {
                    continue;
                }
            }

            // Check daily request budget
            if ($cred->daily_request_budget && $cred->current_daily_requests >= $cred->daily_request_budget) {
                continue;
            }

            // Check daily token budget
            if ($cred->daily_token_budget && $cred->current_daily_tokens >= $cred->daily_token_budget) {
                continue;
            }

            // Check monthly spending budget
            if ($cred->monthly_spending_budget && (float) $cred->current_monthly_spend_usd >= (float) $cred->monthly_spending_budget) {
                continue;
            }

            $eligible->push($cred);
        }

        return $eligible;
    }

    /**
     * Put a credential into cooldown due to rate limit or temporary provider error.
     */
    public function applyCooldown(AIProviderCredential $credential, int $seconds, string $reason): void
    {
        $credential->update([
            'status' => 'cooldown',
            'cooldown_expires_at' => now()->addSeconds($seconds),
            'last_failed_request_at' => now(),
            'error_count' => $credential->error_count + 1,
            'last_error_reason' => $reason,
        ]);
    }

    /**
     * Record a successful request on a credential.
     */
    public function recordSuccess(AIProviderCredential $credential): void
    {
        $credential->update([
            'last_successful_request_at' => now(),
            'error_count' => 0,
            'last_error_reason' => null,
        ]);
    }
}
