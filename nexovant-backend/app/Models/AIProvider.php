<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AIProvider extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ai_providers';

    protected $fillable = [
        'name',
        'slug',
        'api_endpoint',
        'is_active',
        'priority',
        'circuit_state', // closed, half_open, open
        'consecutive_failures',
        'circuit_tripped_at',
        'provider_budget_monthly_usd',
        'current_monthly_spend_usd',
        'default_routing_strategy',
        'default_model',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
            'consecutive_failures' => 'integer',
            'circuit_tripped_at' => 'datetime',
            'provider_budget_monthly_usd' => 'decimal:4',
            'current_monthly_spend_usd' => 'decimal:4',
        ];
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(AIProviderCredential::class, 'provider_id');
    }

    public function activeCredentials(): HasMany
    {
        return $this->hasMany(AIProviderCredential::class, 'provider_id')
            ->where('status', 'active')
            ->where('is_valid', true)
            ->where('routing_eligibility', true);
    }

    public function models(): HasMany
    {
        return $this->hasMany(AIModel::class, 'provider_id');
    }

    public function quota(): HasOne
    {
        return $this->hasOne(AIProviderQuota::class, 'provider_id');
    }

    public function isCircuitClosed(): bool
    {
        return $this->circuit_state === 'closed';
    }
}
