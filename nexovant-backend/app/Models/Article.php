<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'source_id',
        'cluster_id',
        'title',
        'slug',
        'canonical_url',
        'content',
        'content_raw',
        'content_normalized',
        'content_hash',
        'published_at',
        'verification_status', // confirmed, reported_reliable, developing, disputed, unverified, retracted
        'importance_score',
        'is_breaking',
        'is_editorial_approved',
        'status', // DRAFT, REVIEW, SCHEDULED, PUBLISHED, UNPUBLISHED, ARCHIVED
        'scheduled_at',
        'featured_image',
        'hero_image',
        'featured_image_caption',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'lock_category',
        'lock_topic',
        'lock_content',
        'lock_image',
        'lock_tags',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'importance_score' => 'decimal:2',
            'is_breaking' => 'boolean',
            'is_editorial_approved' => 'boolean',
            'lock_category' => 'boolean',
            'lock_topic' => 'boolean',
            'lock_content' => 'boolean',
            'lock_image' => 'boolean',
            'lock_tags' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Article $article): void {
            if (empty($article->content_raw) && !empty($article->content)) {
                $article->content_raw = $article->content;
            }
            if (empty($article->content_normalized) && !empty($article->content_raw)) {
                $article->content_normalized = $article->content_raw;
            }
            if (empty($article->content_hash)) {
                $article->content_hash = hash('sha256', ($article->slug ?? '') . '|' . ($article->title ?? ''));
            }
        });
    }

    public function getContentAttribute(): string
    {
        return $this->content_normalized ?? $this->content_raw ?? '';
    }

    public function setContentAttribute(string $value): void
    {
        $this->attributes['content_raw'] = $value;
        $this->attributes['content_normalized'] = $value;
    }

    public function getHeroImageAttribute(): ?string
    {
        return $this->featured_image;
    }

    public function setHeroImageAttribute(?string $value): void
    {
        $this->attributes['featured_image'] = $value;
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'source_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'article_categories');
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'article_topics');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'article_tags');
    }

    public function series(): BelongsToMany
    {
        return $this->belongsToMany(EditorialSeries::class, 'article_series')
            ->withPivot('order_in_series');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'article_products');
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'article_companies')
            ->withPivot('relevance');
    }

    public function summary(): HasOne
    {
        return $this->hasOne(ArticleSummary::class);
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(ArticleAssessment::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(ArticleSource::class, 'article_id');
    }

    public function cluster(): HasOne
    {
        return $this->hasOne(StoryCluster::class, 'article_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ArticleRevision::class)->orderByDesc('version_number');
    }

    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_articles')
            ->withPivot('notes')
            ->withTimestamps();
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereIn('verification_status', ['confirmed', 'reported_reliable']);
    }

    public function scopeBreaking(Builder $query): Builder
    {
        return $query->where('is_breaking', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'PUBLISHED')
            ->where('is_editorial_approved', true)
            ->where('published_at', '<=', now());
    }
}
