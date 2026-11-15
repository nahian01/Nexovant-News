<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class IntelligenceEntity extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'intelligence_entities';

    protected $fillable = [
        'entity_type',
        'canonical_name',
        'slug',
        'legal_name',
        'description',
        'website_url',
        'country',
        'headquarters',
        'industry',
        'founding_year',
        'confidence_score',
        'verification_status',
        'merged_into_id',
        'entityable_type',
        'entityable_id',
        'metadata',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'float',
            'founding_year' => 'integer',
            'metadata' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (IntelligenceEntity $entity): void {
            if (empty($entity->slug)) {
                $base = Str::slug($entity->canonical_name ?: 'entity');
                $entity->slug = $base . '-' . Str::lower(Str::random(5));
            }
            if (empty($entity->first_seen_at)) {
                $entity->first_seen_at = now();
            }
            if (empty($entity->last_seen_at)) {
                $entity->last_seen_at = now();
            }
        });
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(EntityAlias::class, 'entity_id');
    }

    public function outgoingRelationships(): HasMany
    {
        return $this->hasMany(EntityRelationship::class, 'source_entity_id');
    }

    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(EntityRelationship::class, 'target_entity_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(EntityHistory::class, 'entity_id')->orderByDesc('created_at');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(EntityResolutionReview::class, 'entity_id');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function mergedEntities(): HasMany
    {
        return $this->hasMany(self::class, 'merged_into_id');
    }

    public function entityable(): MorphTo
    {
        return $this->morphTo();
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(IntelligenceEvent::class, 'intelligence_event_entities', 'entity_id', 'event_id')
            ->withPivot('role');
    }

    public function trends(): BelongsToMany
    {
        return $this->belongsToMany(IntelligenceTrend::class, 'intelligence_trend_entities', 'entity_id', 'trend_id')
            ->withPivot('relevance_weight')
            ->withTimestamps();
    }

    public function addAlias(string $alias, string $type = 'alternative_name', ?string $source = null, float $confidence = 1.0): EntityAlias
    {
        return $this->aliases()->updateOrCreate(
            ['alias' => trim($alias)],
            [
                'alias_type' => $type,
                'source' => $source,
                'confidence_score' => $confidence,
            ]
        );
    }
}
