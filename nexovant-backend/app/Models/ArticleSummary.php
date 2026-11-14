<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleSummary extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'article_id',
        'executive_summary',
        'key_takeaways',
        'model_version',
    ];

    protected function casts(): array
    {
        return [
            'key_takeaways' => 'array',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
