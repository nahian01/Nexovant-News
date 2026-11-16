<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnomalyObservation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'anomaly_observations';

    protected $fillable = [
        'signal_id',
        'subject_type',
        'subject_id',
        'subject_name',
        'indicator_name',
        'baseline_period_days',
        'observation_period_days',
        'observed_value',
        'expected_value',
        'deviation_score',
        'coverage_vs_reality_type',
        'is_suppressed',
        'detection_method',
        'data_sufficiency',
        'sample_count',
        'min_sample_threshold',
        'limitations',
        'detected_at',
    ];

    protected function casts(): array
    {
        return [
            'baseline_period_days' => 'integer',
            'observation_period_days' => 'integer',
            'observed_value' => 'float',
            'expected_value' => 'float',
            'deviation_score' => 'float',
            'is_suppressed' => 'boolean',
            'sample_count' => 'integer',
            'min_sample_threshold' => 'integer',
            'detected_at' => 'datetime',
        ];
    }

    public function signal(): BelongsTo
    {
        return $this->belongsTo(IntelligenceSignal::class, 'signal_id');
    }
}
