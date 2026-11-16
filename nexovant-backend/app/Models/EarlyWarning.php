<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EarlyWarning extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'early_warnings';

    protected $fillable = [
        'signal_id',
        'title',
        'slug',
        'warning_level',
        'status',
        'factor_breakdown',
        'explanation',
        'recommended_watch_points',
        'organization_id',
    ];

    protected function casts(): array
    {
        return [
            'factor_breakdown' => 'array',
            'explanation' => 'array',
            'recommended_watch_points' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EarlyWarning $warning): void {
            if (empty($warning->slug)) {
                $base = Str::slug(substr($warning->title, 0, 70));
                $warning->slug = $base . '-' . Str::lower(Str::random(5));
            }
        });
    }

    public function signal(): BelongsTo
    {
        return $this->belongsTo(IntelligenceSignal::class, 'signal_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(EarlyWarningHistory::class, 'warning_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
