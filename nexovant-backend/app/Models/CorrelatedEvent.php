<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrelatedEvent extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'correlated_events';

    protected $fillable = [
        'primary_event_title',
        'primary_event_id',
        'correlated_event_title',
        'correlated_event_id',
        'correlation_type',
        'correlation_strength',
        'is_causation_claimed',
        'evidence_summary',
        'supporting_sources',
        'alternative_explanations',
        'unresolved_questions',
        'organization_id',
    ];

    protected function casts(): array
    {
        return [
            'correlation_strength' => 'float',
            'is_causation_claimed' => 'boolean', // Always false
            'supporting_sources' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
