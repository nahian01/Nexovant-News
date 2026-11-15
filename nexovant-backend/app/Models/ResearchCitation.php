<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchCitation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'research_citations';

    protected $fillable = [
        'report_id',
        'citation_number',
        'claim_text',
        'fact_id',
        'story_id',
        'source_id',
        'source_name',
        'source_url',
        'published_at',
        'is_verified',
    ];

    protected function casts(): array
    {
        return [
            'citation_number' => 'integer',
            'published_at' => 'datetime',
            'is_verified' => 'boolean',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ResearchReport::class, 'report_id');
    }

    public function fact(): BelongsTo
    {
        return $this->belongsTo(ExtractedFact::class, 'fact_id');
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
