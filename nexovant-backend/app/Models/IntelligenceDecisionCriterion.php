<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntelligenceDecisionCriterion extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'intelligence_decision_criteria';

    protected $fillable = [
        'decision_id',
        'criterion_name',
        'weight',
        'description',
        'scoring_methodology',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
        ];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(IntelligenceDecision::class, 'decision_id');
    }
}
