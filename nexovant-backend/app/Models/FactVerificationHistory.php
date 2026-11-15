<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactVerificationHistory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'fact_verification_histories';

    protected $fillable = [
        'fact_id',
        'user_id',
        'action',
        'previous_status',
        'new_status',
        'editorial_reason',
        'dimension_scores',
    ];

    protected function casts(): array
    {
        return [
            'dimension_scores' => 'array',
        ];
    }

    public function fact(): BelongsTo
    {
        return $this->belongsTo(ExtractedFact::class, 'fact_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
