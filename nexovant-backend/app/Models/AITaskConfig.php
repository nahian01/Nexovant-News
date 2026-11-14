<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AITaskConfig extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ai_task_configs';

    protected $fillable = [
        'task_type',
        'display_name',
        'preferred_provider_id',
        'preferred_model_id',
        'fallback_provider_ids',
        'fallback_model_ids',
        'temperature',
        'max_output_tokens',
        'timeout_seconds',
        'retry_policy',
        'budget_limit_usd',
        'priority',
        'output_format',
        'system_prompt_template',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fallback_provider_ids' => 'array',
            'fallback_model_ids' => 'array',
            'retry_policy' => 'array',
            'temperature' => 'decimal:2',
            'max_output_tokens' => 'integer',
            'timeout_seconds' => 'integer',
            'priority' => 'integer',
            'budget_limit_usd' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function preferredProvider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'preferred_provider_id');
    }

    public function preferredModel(): BelongsTo
    {
        return $this->belongsTo(AIModel::class, 'preferred_model_id');
    }
}
