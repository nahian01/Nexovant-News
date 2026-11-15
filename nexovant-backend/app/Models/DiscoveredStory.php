<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscoveredStory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'discovered_stories';

    protected $fillable = [
        'source_id',
        'cluster_id',
        'title',
        'normalized_title',
        'url',
        'canonical_url',
        'author',
        'published_at',
        'summary',
        'content_raw',
        'content_normalized',
        'featured_image',
        'language',
        'content_hash',
        'status', // discovered, clustered, synthesized, duplicate, excluded, failed
        'duplicate_of_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DiscoveredStory $story): void {
            if (empty($story->normalized_title) && !empty($story->title)) {
                $story->normalized_title = \Illuminate\Support\Str::lower(trim($story->title));
            }
            if (empty($story->url) && !empty($story->canonical_url)) {
                $story->url = $story->canonical_url;
            }
            if (empty($story->canonical_url) && !empty($story->url)) {
                $story->canonical_url = $story->url;
            }
            if (empty($story->content_hash) && !empty($story->canonical_url)) {
                $story->content_hash = hash('sha256', ($story->canonical_url) . '|' . ($story->title ?? ''));
            }
        });
    }

    public function getProcessingStatusAttribute(): string
    {
        return $this->status ?? 'discovered';
    }

    public function setProcessingStatusAttribute(string $value): void
    {
        $this->attributes['status'] = $value;
    }

    public function getIsDuplicateAttribute(): bool
    {
        return $this->status === 'duplicate' || !empty($this->duplicate_of_id);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'source_id');
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(StoryCluster::class, 'cluster_id');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(DiscoveredStory::class, 'duplicate_of_id');
    }

    public function duplicates(): HasMany
    {
        return $this->hasMany(DiscoveredStory::class, 'duplicate_of_id');
    }

    public function facts(): HasMany
    {
        return $this->hasMany(ExtractedFact::class, 'story_id');
    }

    public function clusters(): BelongsToMany
    {
        return $this->belongsToMany(StoryCluster::class, 'cluster_stories', 'story_id', 'cluster_id')
            ->withPivot('similarity_score', 'is_primary')
            ->withTimestamps();
    }
}
