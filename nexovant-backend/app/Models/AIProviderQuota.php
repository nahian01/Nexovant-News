<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIProviderQuota extends Model
{
    use HasUuids;

    protected $table = 'ai_provider_quotas';

    protected $fillable = [
        'provider_id',
        'rpm_limit',
        'tpm_limit',
        'daily_request_limit',
        'monthly_token_limit',
        'current_requests_this_minute',
        'current_tokens_this_minute',
        'current_requests_today',
        'current_tokens_this_month',
        'last_minute_reset_at',
        'last_daily_reset_at',
    ];

    protected function casts(): array
    {
        return [
            'rpm_limit' => 'integer',
            'tpm_limit' => 'integer',
            'current_requests_this_minute' => 'integer',
            'current_tokens_this_minute' => 'integer',
            'current_requests_today' => 'integer',
            'current_tokens_this_month' => 'integer',
            'last_minute_reset_at' => 'datetime',
            'last_daily_reset_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'provider_id');
    }
}
