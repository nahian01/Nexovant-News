<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoryCluster extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'story_clusters';

    protected $fillable = [
        'title',
        'headline',
        'slug',
        'summary',
        'status', // discovered, clustered, synthesizing, synthesized, published, archived
        'lead_story_id',
        'article_id',
        'confidence_score',
        'story_count',
        'first_seen_at',
        'last_seen_at',
        'entities',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'decimal:2',
            'story_count' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'entities' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StoryCluster $cluster): void {
            if (empty($cluster->title) && !empty($cluster->headline)) {
                $cluster->title = $cluster->headline;
            }
            if (empty($cluster->slug) && !empty($cluster->title)) {
                $cluster->slug = \Illuminate\Support\Str::slug($cluster->title) . '-' . substr((string) \Illuminate\Support\Str::uuid(), 0, 8);
            }
        });
    }

    public function getHeadlineAttribute(): string
    {
        return $this->title ?? '';
    }

    public function setHeadlineAttribute(string $value): void
    {
        $this->attributes['title'] = $value;
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(DiscoveredStory::class, 'cluster_stories', 'cluster_id', 'story_id')
            ->withPivot('similarity_score', 'is_primary')
            ->withTimestamps();
    }

    public function primaryStories(): HasMany
    {
        return $this->hasMany(DiscoveredStory::class, 'cluster_id');
    }

    public function facts(): HasMany
    {
        return $this->hasMany(ExtractedFact::class, 'cluster_id');
    }
}
