<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIRoutingLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'ai_routing_logs';

    protected $fillable = [
        'task_type',
        'selected_provider_id',
        'selected_model_id',
        'credential_id',
        'user_id',
        'module',
        'fallback_from_provider_id',
        'fallback_reason',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'estimated_cost_usd',
        'currency',
        'latency_ms',
        'retry_count',
        'fallback_attempted',
        'status',
        'error_details',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'total_tokens' => 'integer',
            'retry_count' => 'integer',
            'fallback_attempted' => 'boolean',
            'estimated_cost_usd' => 'decimal:6',
            'latency_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'selected_provider_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AIModel::class, 'selected_model_id');
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(AIProviderCredential::class, 'credential_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
