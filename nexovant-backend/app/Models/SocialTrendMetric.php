<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialTrendMetric extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'trend_id',
        'total_mentions',
        'velocity_per_hour',
        'sentiment_positive_ratio',
        'sentiment_negative_ratio',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'total_mentions' => 'integer',
            'velocity_per_hour' => 'decimal:2',
            'sentiment_positive_ratio' => 'decimal:2',
            'sentiment_negative_ratio' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function trend(): BelongsTo
    {
        return $this->belongsTo(SocialTrend::class, 'trend_id');
    }
}
