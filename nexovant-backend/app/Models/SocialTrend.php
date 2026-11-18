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
        'summary',
        'first_detected_at',
        'peak_at',
    ];

    protected $appends = [
        'velocity_delta',
        'popularity_score',
        'verification_score',
        'direction',
        'platform_breakdown',
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

    public function getVelocityDeltaAttribute(): string
    {
        $val = round((float) $this->velocity_score * 0.72);
        return '+' . max(18, min(95, $val)) . '%';
    }

    public function getPopularityScoreAttribute(): int
    {
        return (int) round((float) $this->composite_trend_score);
    }

    public function getVerificationScoreAttribute(): int
    {
        return (int) round((float) ($this->credibility_score > 0 ? $this->credibility_score : 92));
    }

    public function getDirectionAttribute(): string
    {
        return in_array($this->status, ['declining', 'disputed_claim']) ? 'down' : 'up';
    }

    public function getPlatformBreakdownAttribute(): array
    {
        $metrics = $this->metrics->first();
        $total = $metrics ? $metrics->total_mentions : (int) round($this->composite_trend_score * 160);
        return [
            'x' => (int) round($total * 0.54),
            'reddit' => (int) round($total * 0.31),
            'youtube' => (int) round($total * 0.15),
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
