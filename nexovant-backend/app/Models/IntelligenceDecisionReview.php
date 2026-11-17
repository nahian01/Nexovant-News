<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntelligenceDecisionReview extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'intelligence_decision_reviews';

    protected $fillable = [
        'decision_id',
        'reviewer_id',
        'review_type',
        'notes',
        'changed_assumptions',
        'observed_results_vs_expected',
        'action_taken',
    ];

    protected function casts(): array
    {
        return [
            'changed_assumptions' => 'array',
        ];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(IntelligenceDecision::class, 'decision_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
