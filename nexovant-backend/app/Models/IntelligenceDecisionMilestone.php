<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntelligenceDecisionMilestone extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'intelligence_decision_milestones';

    protected $fillable = [
        'decision_id',
        'title',
        'target_date',
        'status',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(IntelligenceDecision::class, 'decision_id');
    }
}
