<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleRevision extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'article_revisions';

    protected $fillable = [
        'article_id',
        'user_id',
        'version_number',
        'title',
        'content_raw',
        'content_normalized',
        'summary',
        'change_summary',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
