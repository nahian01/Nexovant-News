<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialTrend extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'topic_id',
        'title',
        'status', // emerging, accelerating, trending, viral, declining, confirmed_development, unverified_discussion, disputed_claim
        'composite_trend_score',
        'velocity_score',
        'credibility_score',
        'business_relevance_score',
        'cross_platform_count',
        'first_detected_at',
        'peak_at',
    ];

    protected function casts(): array
    {
        return [
            'composite_trend_score' => 'decimal:2',
            'velocity_score' => 'decimal:2',
            'credibility_score' => 'decimal:2',
            'business_relevance_score' => 'decimal:2',
            'cross_platform_count' => 'integer',
            'first_detected_at' => 'datetime',
            'peak_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(SocialTopic::class, 'topic_id');
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(SocialTrendMetric::class, 'trend_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(SocialTrendSource::class, 'trend_id');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(SocialVerificationRecord::class, 'trend_id');
    }
}
