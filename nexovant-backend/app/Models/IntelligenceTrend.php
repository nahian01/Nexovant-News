<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class IntelligenceTrend extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'intelligence_trends';

    protected $fillable = [
        'name',
        'slug',
        'trend_category',
        'status',
        'composite_score',
        'volume_score',
        'growth_rate_score',
        'source_diversity_score',
        'source_credibility_score',
        'entity_relevance_score',
        'story_count',
        'independent_sources_count',
        'first_observed_at',
        'last_observed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'composite_score' => 'float',
            'volume_score' => 'float',
            'growth_rate_score' => 'float',
            'source_diversity_score' => 'float',
            'source_credibility_score' => 'float',
            'entity_relevance_score' => 'float',
            'story_count' => 'integer',
            'independent_sources_count' => 'integer',
            'first_observed_at' => 'datetime',
            'last_observed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (IntelligenceTrend $trend): void {
            if (empty($trend->slug)) {
                $base = Str::slug(substr($trend->name, 0, 70));
                $trend->slug = $base . '-' . Str::lower(Str::random(5));
            }
            if (empty($trend->first_observed_at)) {
                $trend->first_observed_at = now();
            }
            if (empty($trend->last_observed_at)) {
                $trend->last_observed_at = now();
            }
        });
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(IntelligenceEntity::class, 'intelligence_trend_entities', 'trend_id', 'entity_id')
            ->withPivot('relevance_weight')
            ->withTimestamps();
    }
}
