<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class IntelligenceEvent extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'intelligence_events';

    protected $fillable = [
        'title',
        'slug',
        'event_category',
        'summary',
        'occurred_at',
        'reported_at',
        'discovered_at',
        'verification_status',
        'primary_entity_id',
        'cluster_id',
        'article_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'reported_at' => 'datetime',
            'discovered_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (IntelligenceEvent $event): void {
            if (empty($event->slug)) {
                $base = Str::slug(substr($event->title, 0, 70));
                $event->slug = $base . '-' . Str::lower(Str::random(5));
            }
            if (empty($event->discovered_at)) {
                $event->discovered_at = now();
            }
            if (empty($event->reported_at)) {
                $event->reported_at = now();
            }
        });
    }

    public function primaryEntity(): BelongsTo
    {
        return $this->belongsTo(IntelligenceEntity::class, 'primary_entity_id');
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(StoryCluster::class, 'cluster_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(IntelligenceEntity::class, 'intelligence_event_entities', 'event_id', 'entity_id')
            ->withPivot('role');
    }
}
