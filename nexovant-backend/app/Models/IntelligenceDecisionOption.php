<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntelligenceDecisionOption extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'intelligence_decision_options';

    protected $fillable = [
        'decision_id',
        'option_name',
        'description',
        'advantages',
        'disadvantages',
        'risks',
        'assumptions',
        'evidence_references',
        'expected_outcomes',
        'score',
        'is_selected',
    ];

    protected function casts(): array
    {
        return [
            'advantages' => 'array',
            'disadvantages' => 'array',
            'risks' => 'array',
            'assumptions' => 'array',
            'evidence_references' => 'array',
            'score' => 'float',
            'is_selected' => 'boolean',
        ];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(IntelligenceDecision::class, 'decision_id');
    }
}
