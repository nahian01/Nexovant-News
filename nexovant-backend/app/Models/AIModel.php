<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIModel extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ai_models';

    protected $fillable = [
        'provider_id',
        'model_identifier',
        'display_name',
        'cost_per_1k_input_tokens',
        'cost_per_1k_output_tokens',
        'context_window',
        'max_output_tokens',
        'supports_structured_output',
        'supports_tool_calling',
        'supports_vision',
        'supports_embedding',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_per_1k_input_tokens' => 'decimal:5',
            'cost_per_1k_output_tokens' => 'decimal:5',
            'context_window' => 'integer',
            'max_output_tokens' => 'integer',
            'supports_structured_output' => 'boolean',
            'supports_tool_calling' => 'boolean',
            'supports_vision' => 'boolean',
            'supports_embedding' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'provider_id');
    }
}
