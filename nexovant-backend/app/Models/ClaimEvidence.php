<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimEvidence extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'claim_evidences';

    protected $fillable = [
        'fact_id',
        'source_id',
        'story_id',
        'stance',
        'evidence_text',
        'source_url',
        'published_at',
        'is_syndicated_reprint',
        'reliability_score',
        'reviewer_decision',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_syndicated_reprint' => 'boolean',
            'reliability_score' => 'float',
        ];
    }

    public function fact(): BelongsTo
    {
        return $this->belongsTo(ExtractedFact::class, 'fact_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'source_id');
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(DiscoveredStory::class, 'story_id');
    }
}
