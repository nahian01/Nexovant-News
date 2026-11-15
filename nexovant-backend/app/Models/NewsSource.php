<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsSource extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'news_sources';

    protected $fillable = [
        'name',
        'publisher_name',
        'description',
        'slug',
        'source_type',
        'source_category',
        'geographic_region',
        'language',
        'url',
        'website_url',
        'feed_url',
        'credibility_score',
        'trust_tier',
        'is_active',
        'rate_limit_per_minute',
        'max_items_per_fetch',
        'timeout_seconds',
        'etag',
        'last_modified_header',
        'source_config',
        'last_polled_at',
        'last_successful_fetch_at',
        'last_attempted_fetch_at',
        'health_status',
        'last_error_message',
        'fetch_interval_minutes',
        'consecutive_failures',
        'total_items_ingested',
    ];

    protected function casts(): array
    {
        return [
            'credibility_score' => 'decimal:2',
            'is_active' => 'boolean',
            'rate_limit_per_minute' => 'integer',
            'max_items_per_fetch' => 'integer',
            'timeout_seconds' => 'integer',
            'fetch_interval_minutes' => 'integer',
            'consecutive_failures' => 'integer',
            'total_items_ingested' => 'integer',
            'source_config' => 'array',
            'last_polled_at' => 'datetime',
            'last_successful_fetch_at' => 'datetime',
            'last_attempted_fetch_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NewsSource $source): void {
            if (empty($source->slug) && !empty($source->name)) {
                $source->slug = \Illuminate\Support\Str::slug($source->name);
            }
        });
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'source_id');
    }

    public function discoveredStories(): HasMany
    {
        return $this->hasMany(DiscoveredStory::class, 'source_id');
    }

    public function facts(): HasMany
    {
        return $this->hasMany(ExtractedFact::class, 'source_id');
    }

    public function pipelineJobs(): HasMany
    {
        return $this->hasMany(PipelineJob::class, 'source_id');
    }
}
