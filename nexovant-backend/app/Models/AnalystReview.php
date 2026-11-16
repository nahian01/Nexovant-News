<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalystReview extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'analyst_reviews';

    protected $fillable = [
        'reviewable_type',
        'reviewable_id',
        'analyst_user_id',
        'decision',
        'feedback_notes',
        'added_evidence',
        'suggested_threshold_adjustments',
        'is_applied_to_rules',
    ];

    protected function casts(): array
    {
        return [
            'added_evidence' => 'array',
            'suggested_threshold_adjustments' => 'array',
            'is_applied_to_rules' => 'boolean',
        ];
    }

    public function analyst(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analyst_user_id');
    }
}
