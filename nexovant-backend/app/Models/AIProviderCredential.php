<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIProviderCredential extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ai_provider_credentials';

    protected $fillable = [
        'provider_id',
        'label',
        'display_name',
        'encrypted_api_key',
        'key_fingerprint',
        'account_label',
        'allowed_models',
        'status', // active, inactive, suspended, cooldown, rate_limited
        'priority',
        'routing_eligibility',
        'rate_limit_config',
        'daily_request_budget',
        'daily_token_budget',
        'monthly_spending_budget',
        'current_daily_requests',
        'current_daily_tokens',
        'current_monthly_spend_usd',
        'is_valid',
        'last_tested_at',
        'last_successful_request_at',
        'last_failed_request_at',
        'last_health_check_at',
        'error_count',
        'cooldown_expires_at',
        'last_error_reason',
    ];

    protected $hidden = [
        'encrypted_api_key',
    ];

    protected function casts(): array
    {
        return [
            'is_valid' => 'boolean',
            'routing_eligibility' => 'boolean',
            'allowed_models' => 'array',
            'rate_limit_config' => 'array',
            'priority' => 'integer',
            'daily_request_budget' => 'integer',
            'daily_token_budget' => 'integer',
            'current_daily_requests' => 'integer',
            'current_daily_tokens' => 'integer',
            'error_count' => 'integer',
            'monthly_spending_budget' => 'decimal:4',
            'current_monthly_spend_usd' => 'decimal:4',
            'last_tested_at' => 'datetime',
            'last_successful_request_at' => 'datetime',
            'last_failed_request_at' => 'datetime',
            'last_health_check_at' => 'datetime',
            'cooldown_expires_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'provider_id');
    }

    public function routingLogs(): HasMany
    {
        return $this->hasMany(AIRoutingLog::class, 'credential_id');
    }

    /**
     * Check if credential is currently eligible for routing.
     */
    public function isEligible(): bool
    {
        if ($this->status !== 'active' || !$this->routing_eligibility || !$this->is_valid) {
            return false;
        }

        if ($this->cooldown_expires_at && $this->cooldown_expires_at->isFuture()) {
            return false;
        }

        if ($this->daily_request_budget && $this->current_daily_requests >= $this->daily_request_budget) {
            return false;
        }

        if ($this->daily_token_budget && $this->current_daily_tokens >= $this->daily_token_budget) {
            return false;
        }

        if ($this->monthly_spending_budget && $this->current_monthly_spend_usd >= $this->monthly_spending_budget) {
            return false;
        }

        return true;
    }
}
