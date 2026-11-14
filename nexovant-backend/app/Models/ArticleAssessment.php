<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleAssessment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'article_id',
        'what_happened',
        'when_happened',
        'why_happened',
        'business_significance',
        'who_affected',
        'potential_consequences',
        'what_to_monitor_next',
        'confidence_score',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'decimal:2',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
