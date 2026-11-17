<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IntelligenceRisk extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'intelligence_risks';

    protected $fillable = [
        'title',
        'slug',
        'organization_id',
        'user_id',
        'risk_type',
        'category',
        'affected_entities',
        'geographic_scope',
        'description',
        'supporting_evidence',
        'potential_impact',
        'likelihood_score',
        'severity',
        'time_horizon',
        'confidence',
        'assumptions',
        'recommended_monitoring_indicators',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'affected_entities' => 'array',
            'supporting_evidence' => 'array',
            'likelihood_score' => 'float',
            'assumptions' => 'array',
            'recommended_monitoring_indicators' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (IntelligenceRisk $model) {
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
}
