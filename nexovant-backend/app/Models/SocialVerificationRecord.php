<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialVerificationRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'trend_id',
        'related_article_id',
        'verification_verdict',
        'audited_reason',
        'reviewed_by_user_id',
    ];

    public function trend(): BelongsTo
    {
        return $this->belongsTo(SocialTrend::class, 'trend_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'related_article_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
