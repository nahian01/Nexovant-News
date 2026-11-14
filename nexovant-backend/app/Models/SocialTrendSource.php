<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialTrendSource extends Model
{
    use HasUuids;

    protected $fillable = [
        'trend_id',
        'platform',
        'sample_url',
        'sample_text',
        'engagement_volume',
    ];

    protected function casts(): array
    {
        return [
            'engagement_volume' => 'integer',
        ];
    }

    public function trend(): BelongsTo
    {
        return $this->belongsTo(SocialTrend::class, 'trend_id');
    }
}
