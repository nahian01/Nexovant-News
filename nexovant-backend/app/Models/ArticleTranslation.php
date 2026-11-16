<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleTranslation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'article_translations';

    protected $fillable = [
        'article_id',
        'original_language',
        'detected_language',
        'target_language',
        'translated_title',
        'translated_content',
        'translated_summary',
        'translation_provider',
        'translation_model',
        'quality_confidence_score',
        'status',
        'translated_at',
    ];

    protected function casts(): array
    {
        return [
            'quality_confidence_score' => 'float',
            'translated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ArticleTranslation $trans): void {
            if (empty($trans->original_language)) {
                $trans->original_language = $trans->article?->original_language ?: ($trans->article?->detected_language ?: 'en');
            }
        });
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }
}
