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
        'slug',
        'source_type',
        'url',
        'feed_url',
        'credibility_score',
        'is_active',
        'rate_limit_per_minute',
        'last_polled_at',
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
            'fetch_interval_minutes' => 'integer',
            'consecutive_failures' => 'integer',
            'total_items_ingested' => 'integer',
            'last_polled_at' => 'datetime',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'source_id');
    }

    public function pipelineJobs(): HasMany
    {
        return $this->hasMany(PipelineJob::class, 'source_id');
    }
}
