<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntelligenceImpactAssessment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'intelligence_impact_assessments';

    protected $fillable = [
        'signal_id',
        'early_warning_id',
        'dimension',
        'impact_severity',
        'time_horizon',
        'affected_entities',
        'affected_sectors',
        'supporting_evidence',
        'potential_consequences',
        'alternative_interpretations',
        'uncertainty_and_limitations',
    ];

    protected function casts(): array
    {
        return [
            'affected_entities' => 'array',
            'affected_sectors' => 'array',
        ];
    }

    public function signal(): BelongsTo
    {
        return $this->belongsTo(IntelligenceSignal::class, 'signal_id');
    }

    public function earlyWarning(): BelongsTo
    {
        return $this->belongsTo(EarlyWarning::class, 'early_warning_id');
    }
}
