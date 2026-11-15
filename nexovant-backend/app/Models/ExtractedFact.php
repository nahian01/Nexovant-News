<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractedFact extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'extracted_facts';

    protected $fillable = [
        'cluster_id',
        'story_id',
        'source_id',
        'claim',
        'entity',
        'confidence_score',
        'verification_status', // supported_multiple, supported_single, conflicting, unverified, contradicted
        'evidence_excerpt',
        'source_url',
        'published_at',
        'editorial_notes',
        'verified_by_user_id',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'decimal:2',
            'published_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(StoryCluster::class, 'cluster_id');
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(DiscoveredStory::class, 'story_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'source_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function evidences(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClaimEvidence::class, 'fact_id');
    }

    public function histories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FactVerificationHistory::class, 'fact_id')->orderByDesc('created_at');
    }
}
