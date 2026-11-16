<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\Article;
use App\Models\IntelligenceTrend;
use App\Models\TrendForecast;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PredictiveTrendService
{
    public const MIN_HISTORICAL_POINTS = 5;

    /**
     * Generate or update a predictive forecast for a topic or technology trend.
     *
     * @param string $subject Topic or technology name
     * @param string $category technology_adoption, capex_expansion, supply_chain_velocity, regulatory_timeline
     * @param string $horizon 30d, 90d, 180d, 1y
     * @param string|null $tenantId
     * @return TrendForecast
     */
    public function generateForecast(
        string $subject,
        string $category = 'technology_adoption',
        string $horizon = '90d',
        ?string $tenantId = null
    ): TrendForecast {
        $windowDays = 90;
        $now = now();

        // 1. Collect historical time-series observation points (weekly buckets)
        $historicalPoints = $this->collectWeeklyDataPoints($subject, $windowDays);
        $activePeriods = count(array_filter($historicalPoints, fn($p) => $p['value'] > 0));
        $totalObservations = array_sum(array_column($historicalPoints, 'value'));

        // 2. Insufficient Data Guardrail: Never fabricate predictive trajectories without sufficient samples
        if ($activePeriods < self::MIN_HISTORICAL_POINTS || $totalObservations < 5) {
            return TrendForecast::create([
                'subject' => $subject,
                'forecast_category' => $category,
                'forecast_horizon' => $horizon,
                'historical_window_days' => $windowDays,
                'methodology' => 'linear_trend_extrapolation',
                'historical_data_points' => $historicalPoints,
                'forecasted_values' => [],
                'prediction_intervals' => [],
                'assumptions' => [
                    'stationary_reporting_rate' => false,
                    'minimum_historical_periods_required' => self::MIN_HISTORICAL_POINTS,
                ],
                'limitations' => "Insufficient historical observations ({$activePeriods} active periods observed < " . self::MIN_HISTORICAL_POINTS . " required). Reliable predictive modeling requires empirical multi-period historical time-series.",
                'is_supported' => false,
                'data_sufficiency_status' => 'insufficient_sample_size',
                'evaluation_metrics' => null,
                'organization_id' => $tenantId,
            ]);
        }

        // 3. Compute Linear Trend Parameters (Slope & Intercept via Ordinary Least Squares)
        $n = count($historicalPoints);
        $xVals = range(0, $n - 1);
        $yVals = array_column($historicalPoints, 'value');

        $xMean = array_sum($xVals) / $n;
        $yMean = array_sum($yVals) / $n;

        $numerator = 0.0;
        $denominator = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $numerator += ($xVals[$i] - $xMean) * ($yVals[$i] - $yMean);
            $denominator += pow($xVals[$i] - $xMean, 2);
        }

        $slope = $denominator > 0 ? ($numerator / $denominator) : 0.0;
        $intercept = $yMean - ($slope * $xMean);

        // Backtesting error metrics (In-sample MAE & RMSE)
        $residuals = [];
        for ($i = 0; $i < $n; $i++) {
            $predicted = $intercept + ($slope * $xVals[$i]);
            $residuals[] = abs($yVals[$i] - $predicted);
        }
        $mae = round(array_sum($residuals) / $n, 2);
        $rmse = round(sqrt(array_sum(array_map(fn($r) => pow($r, 2), $residuals)) / $n), 2);

        // Standard error of estimate
        $stdError = max(1.0, $rmse);

        // 4. Generate Projected Steps into Horizon
        $steps = match ($horizon) {
            '30d' => 4,
            '90d' => 12,
            '180d' => 24,
            '1y' => 52,
            default => 12
        };

        $forecastedValues = [];
        $predictionIntervals = [];

        $lastDate = Carbon::parse($historicalPoints[$n - 1]['date']);

        for ($step = 1; $step <= $steps; $step++) {
            $targetX = ($n - 1) + $step;
            $meanProjection = max(0.0, round($intercept + ($slope * $targetX), 2));
            $targetDate = $lastDate->copy()->addWeeks($step)->format('Y-m-d');

            // 95% Confidence Interval bounds (z = 1.96)
            $margin = round(1.96 * $stdError * sqrt(1 + (1.0 / $n) + (pow($targetX - $xMean, 2) / max(1.0, $denominator))), 2);
            $lowerBound = max(0.0, round($meanProjection - $margin, 2));
            $upperBound = round($meanProjection + $margin, 2);

            $forecastedValues[] = [
                'step' => $step,
                'date' => $targetDate,
                'projected_value' => $meanProjection,
            ];

            $predictionIntervals[] = [
                'step' => $step,
                'date' => $targetDate,
                'lower_bound_95' => $lowerBound,
                'upper_bound_95' => $upperBound,
            ];
        }

        return TrendForecast::create([
            'subject' => $subject,
            'forecast_category' => $category,
            'forecast_horizon' => $horizon,
            'historical_window_days' => $windowDays,
            'methodology' => 'linear_trend_extrapolation_with_ols',
            'historical_data_points' => $historicalPoints,
            'forecasted_values' => $forecastedValues,
            'prediction_intervals' => $predictionIntervals,
            'assumptions' => [
                'linear_velocity_continuation' => true,
                'constant_source_coverage' => true,
                'no_black_swan_regulatory_embargo' => true,
            ],
            'limitations' => "Projections represent statistical trend extrapolation based on empirical multi-week reporting volume. External non-linear policy shifts or unforeseen structural bottlenecks may cause actual outcomes to diverge from projected mean.",
            'is_supported' => true,
            'data_sufficiency_status' => 'sufficient',
            'evaluation_metrics' => [
                'mae' => $mae,
                'rmse' => $rmse,
                'in_sample_r_squared' => $denominator > 0 ? round(pow($numerator, 2) / ($denominator * max(1.0, array_sum(array_map(fn($y) => pow($y - $yMean, 2), $yVals)))), 3) : 0.0,
            ],
            'organization_id' => $tenantId,
        ]);
    }

    /**
     * Collect weekly aggregated counts for the subject over window.
     */
    protected function collectWeeklyDataPoints(string $subject, int $windowDays): array
    {
        $points = [];
        $weeks = (int) ceil($windowDays / 7.0);
        $start = now()->subDays($windowDays);

        for ($w = 0; $w < $weeks; $w++) {
            $weekStart = $start->copy()->addWeeks($w);
            $weekEnd = $weekStart->copy()->addWeek();

            $count = Article::where('published_at', '>=', $weekStart)
                ->where('published_at', '<', $weekEnd)
                ->where(function ($q) use ($subject) {
                    $q->where('title', 'like', "%{$subject}%")
                      ->orWhere('content_normalized', 'like', "%{$subject}%");
                })
                ->count();

            $points[] = [
                'week' => $w + 1,
                'date' => $weekStart->format('Y-m-d'),
                'value' => (float) $count,
            ];
        }

        return $points;
    }
}
