<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchBrief extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'research_briefs';

    protected $fillable = [
        'user_id',
        'organization_id',
        'collection_id',
        'title',
        'research_question',
        'executive_summary',
        'structured_sections',
        'citations',
        'conflicting_evidence',
        'confidence_score',
        'uncertainty_rating',
        'is_shared_with_org',
    ];

    protected function casts(): array
    {
        return [
            'structured_sections' => 'array',
            'citations' => 'array',
            'conflicting_evidence' => 'array',
            'confidence_score' => 'float',
            'is_shared_with_org' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(SubscriberCollection::class, 'collection_id');
    }
}
