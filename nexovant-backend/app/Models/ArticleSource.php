<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleSource extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'article_sources';

    protected $fillable = [
        'article_id',
        'story_id',
        'source_id',
        'publisher_name',
        'source_url',
        'source_published_at',
    ];

    protected function casts(): array
    {
        return [
            'source_published_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(DiscoveredStory::class, 'story_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'source_id');
    }
}
