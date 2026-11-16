<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendForecast extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'trend_forecasts';

    protected $fillable = [
        'trend_id',
        'subject',
        'forecast_category',
        'forecast_horizon',
        'historical_window_days',
        'methodology',
        'historical_data_points',
        'forecasted_values',
        'prediction_intervals',
        'assumptions',
        'limitations',
        'is_supported',
        'data_sufficiency_status',
        'evaluation_metrics',
        'backtesting_metrics',
        'holdout_evaluation',
        'organization_id',
    ];

    protected function casts(): array
    {
        return [
            'historical_window_days' => 'integer',
            'historical_data_points' => 'array',
            'forecasted_values' => 'array',
            'prediction_intervals' => 'array',
            'assumptions' => 'array',
            'is_supported' => 'boolean',
            'evaluation_metrics' => 'array',
            'backtesting_metrics' => 'array',
            'holdout_evaluation' => 'array',
        ];
    }

    public function trend(): BelongsTo
    {
        return $this->belongsTo(IntelligenceTrend::class, 'trend_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
