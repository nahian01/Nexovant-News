<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class IntelligenceSignal extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'intelligence_signals';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'signal_category',
        'severity',
        'status',
        'related_entities',
        'related_topics',
        'geographic_scope',
        'first_observed_at',
        'last_updated_at',
        'evidence_count',
        'independent_sources_count',
        'verification_status',
        'detection_methodology',
        'review_status',
        'reviewer_id',
        'review_notes',
        'reviewed_at',
        'analysis_history',
        'organization_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'related_entities' => 'array',
            'related_topics' => 'array',
            'analysis_history' => 'array',
            'metadata' => 'array',
            'evidence_count' => 'integer',
            'independent_sources_count' => 'integer',
            'first_observed_at' => 'datetime',
            'last_updated_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (IntelligenceSignal $signal): void {
            if (empty($signal->slug)) {
                $base = Str::slug(substr($signal->title, 0, 70));
                $signal->slug = $base . '-' . Str::lower(Str::random(5));
            }
            if (empty($signal->first_observed_at)) {
                $signal->first_observed_at = now();
            }
            if (empty($signal->last_updated_at)) {
                $signal->last_updated_at = now();
            }
        });
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(SignalEvidence::class, 'signal_id');
    }

    public function anomalies(): HasMany
    {
        return $this->hasMany(AnomalyObservation::class, 'signal_id');
    }

    public function earlyWarning(): HasOne
    {
        return $this->hasOne(EarlyWarning::class, 'signal_id');
    }

    public function impactAssessments(): HasMany
    {
        return $this->hasMany(IntelligenceImpactAssessment::class, 'signal_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
