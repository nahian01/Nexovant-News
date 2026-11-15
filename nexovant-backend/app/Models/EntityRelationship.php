<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityRelationship extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'entity_relationships';

    protected $fillable = [
        'source_entity_id',
        'relationship_type',
        'target_entity_id',
        'supporting_source_id',
        'story_id',
        'article_id',
        'evidence_excerpt',
        'source_url',
        'valid_from',
        'valid_to',
        'confidence_score',
        'verification_status',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to' => 'date',
            'confidence_score' => 'float',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function sourceEntity(): BelongsTo
    {
        return $this->belongsTo(IntelligenceEntity::class, 'source_entity_id');
    }

    public function targetEntity(): BelongsTo
    {
        return $this->belongsTo(IntelligenceEntity::class, 'target_entity_id');
    }

    public function supportingSource(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'supporting_source_id');
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(DiscoveredStory::class, 'story_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }
}
