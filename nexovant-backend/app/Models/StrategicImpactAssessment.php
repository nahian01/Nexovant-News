<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StrategicImpactAssessment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'strategic_impact_assessments';

    protected $fillable = [
        'title',
        'slug',
        'organization_id',
        'user_id',
        'signal_id',
        'early_warning_id',
        'affected_entities',
        'affected_industry_or_market',
        'geographic_scope',
        'dimension',
        'potential_direction',
        'estimated_magnitude',
        'time_horizon',
        'supporting_evidence',
        'contradictory_evidence',
        'assumptions',
        'confidence_level',
        'uncertainty_explanation',
        'methodology_explanation',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'affected_entities' => 'array',
            'supporting_evidence' => 'array',
            'contradictory_evidence' => 'array',
            'assumptions' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (StrategicImpactAssessment $model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->title) . '-' . Str::random(6);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
