<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityResolutionReview extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'entity_resolution_reviews';

    protected $fillable = [
        'entity_id',
        'candidate_name',
        'candidate_type',
        'potential_match_entity_id',
        'confidence_score',
        'status',
        'match_reason',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'float',
            'reviewed_at' => 'datetime',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(IntelligenceEntity::class, 'entity_id');
    }

    public function potentialMatch(): BelongsTo
    {
        return $this->belongsTo(IntelligenceEntity::class, 'potential_match_entity_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
