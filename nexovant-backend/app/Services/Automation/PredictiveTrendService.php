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
    public const MIN_HOLDOUT_SAMPLE_SIZE = 8;

    /**
     * Generate or update a predictive forecast for a topic or technology trend with walk-forward backtesting.
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
        $n = count($historicalPoints);

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
                'backtesting_metrics' => null,
                'holdout_evaluation' => [
                    'status' => 'rejected_insufficient_data',
                    'sample_size' => $n,
                    'min_required' => self::MIN_HISTORICAL_POINTS,
                ],
                'organization_id' => $tenantId,
            ]);
        }

        // 3. Walk-Forward Holdout Backtesting (Separating Training vs Test Observations)
        $holdoutEvaluation = $this->performWalkForwardBacktest($historicalPoints);

        // 4. Compute Full-Sample Linear Trend Parameters (OLS)
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

        // In-sample error metrics
        $inSampleResiduals = [];
        for ($i = 0; $i < $n; $i++) {
            $predicted = $intercept + ($slope * $xVals[$i]);
            $inSampleResiduals[] = abs($yVals[$i] - $predicted);
        }
        $mae = round(array_sum($inSampleResiduals) / $n, 2);
        $rmse = round(sqrt(array_sum(array_map(fn($r) => pow($r, 2), $inSampleResiduals)) / $n), 2);
        $inSampleRSquared = $denominator > 0 ? round(pow($numerator, 2) / ($denominator * max(1.0, array_sum(array_map(fn($y) => pow($y - $yMean, 2), $yVals)))), 3) : 0.0;

        $stdError = max(1.0, $rmse);

        // 5. Generate Forward Projected Steps into Horizon
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
            'methodology' => 'linear_trend_extrapolation_with_ols_and_walk_forward_backtest',
            'historical_data_points' => $historicalPoints,
            'forecasted_values' => $forecastedValues,
            'prediction_intervals' => $predictionIntervals,
            'assumptions' => [
                'linear_velocity_continuation' => true,
                'constant_source_coverage' => true,
                'no_black_swan_regulatory_embargo' => true,
            ],
            'limitations' => "Projections represent statistical trend extrapolation based on empirical multi-week reporting volume. Holdout evaluations separate historical training from validation observations to guard against overconfidence.",
            'is_supported' => true,
            'data_sufficiency_status' => 'sufficient',
            'evaluation_metrics' => [
                'mae' => $mae,
                'rmse' => $rmse,
                'in_sample_mae' => $mae,
                'in_sample_rmse' => $rmse,
                'in_sample_r_squared' => $inSampleRSquared,
            ],
            'backtesting_metrics' => $holdoutEvaluation['metrics'] ?? null,
            'holdout_evaluation' => $holdoutEvaluation,
            'organization_id' => $tenantId,
        ]);
    }

    /**
     * Perform walk-forward train/test split backtesting.
     */
    protected function performWalkForwardBacktest(array $historicalPoints): array
    {
        $n = count($historicalPoints);
        $activeCount = count(array_filter($historicalPoints, fn($p) => (float) $p['value'] > 0.0));

        // Guardrail: Enforce explicit refusal when active sample size < 8 for holdout splits
        if ($activeCount < self::MIN_HOLDOUT_SAMPLE_SIZE) {
            return [
                'status' => 'insufficient_sample_for_holdout',
                'sample_size' => $activeCount,
                'min_required' => self::MIN_HOLDOUT_SAMPLE_SIZE,
                'message' => "Sample size of {$activeCount} historical active periods is insufficient for robust walk-forward holdout validation (minimum " . self::MIN_HOLDOUT_SAMPLE_SIZE . " required). Reporting in-sample metrics only.",
                'metrics' => null,
            ];
        }

        // Split: Last 4 points for test/holdout, previous points for train
        $testSize = 4;
        $trainSize = $n - $testSize;

        $trainPoints = array_slice($historicalPoints, 0, $trainSize);
        $testPoints = array_slice($historicalPoints, $trainSize);

        // Fit linear regression on training slice
        $xTrain = range(0, $trainSize - 1);
        $yTrain = array_column($trainPoints, 'value');

        $xMean = array_sum($xTrain) / $trainSize;
        $yMean = array_sum($yTrain) / $trainSize;

        $num = 0.0;
        $den = 0.0;
        for ($i = 0; $i < $trainSize; $i++) {
            $num += ($xTrain[$i] - $xMean) * ($yTrain[$i] - $yMean);
            $den += pow($xTrain[$i] - $xMean, 2);
        }

        $slopeTrain = $den > 0 ? ($num / $den) : 0.0;
        $interceptTrain = $yMean - ($slopeTrain * $xMean);

        // Standard error of training model
        $trainResiduals = [];
        for ($i = 0; $i < $trainSize; $i++) {
            $pred = $interceptTrain + ($slopeTrain * $xTrain[$i]);
            $trainResiduals[] = abs($yTrain[$i] - $pred);
        }
        $rmseTrain = sqrt(array_sum(array_map(fn($r) => pow($r, 2), $trainResiduals)) / $trainSize);
        $stdErrorTrain = max(1.0, $rmseTrain);

        // Naive baseline persistence: predict y_{t} for all future steps
        $naiveBaselineValue = (float) end($trainPoints)['value'];

        // Evaluate out-of-sample on holdout test set
        $absErrors = [];
        $sqErrors = [];
        $pctErrors = [];
        $naiveAbsErrors = [];
        $coveredCount = 0;

        $yTestMean = array_sum(array_column($testPoints, 'value')) / $testSize;
        $totalTestSumSquares = 0.0;

        foreach ($testPoints as $j => $point) {
            $actual = (float) $point['value'];
            $xTarget = $trainSize + $j;
            $predicted = max(0.0, round($interceptTrain + ($slopeTrain * $xTarget), 2));

            // 95% confidence interval for holdout point
            $margin = 1.96 * $stdErrorTrain * sqrt(1 + (1.0 / $trainSize) + (pow($xTarget - $xMean, 2) / max(1.0, $den)));
            $lower = max(0.0, $predicted - $margin);
            $upper = $predicted + $margin;

            if ($actual >= $lower && $actual <= $upper) {
                $coveredCount++;
            }

            $e = $actual - $predicted;
            $absErrors[] = abs($e);
            $sqErrors[] = pow($e, 2);
            $pctErrors[] = abs($e) / max(1.0, $actual);

            // Naive error
            $naiveE = $actual - $naiveBaselineValue;
            $naiveAbsErrors[] = abs($naiveE);

            $totalTestSumSquares += pow($actual - $yTestMean, 2);
        }

        $outSampleMae = round(array_sum($absErrors) / $testSize, 2);
        $outSampleRmse = round(sqrt(array_sum($sqErrors) / $testSize), 2);
        $outSampleMape = round((array_sum($pctErrors) / $testSize) * 100.0, 1);
        $naiveMae = round(array_sum($naiveAbsErrors) / $testSize, 2);

        $outSampleRSquared = $totalTestSumSquares > 0
            ? round(1.0 - (array_sum($sqErrors) / $totalTestSumSquares), 3)
            : 0.0;

        $intervalCoveragePct = round(($coveredCount / (float) $testSize) * 100.0, 1);
        $maeImprovementOverNaivePct = $naiveMae > 0
            ? round((($naiveMae - $outSampleMae) / $naiveMae) * 100.0, 1)
            : 0.0;

        $metrics = [
            'out_of_sample_mae' => $outSampleMae,
            'out_of_sample_rmse' => $outSampleRmse,
            'out_of_sample_mape_pct' => $outSampleMape,
            'out_of_sample_r_squared' => $outSampleRSquared,
            'prediction_interval_coverage_pct' => $intervalCoveragePct,
            'naive_baseline_mae' => $naiveMae,
            'mae_improvement_over_naive_pct' => $maeImprovementOverNaivePct,
            'train_sample_size' => $trainSize,
            'holdout_sample_size' => $testSize,
        ];

        return [
            'status' => 'validated',
            'train_sample_size' => $trainSize,
            'holdout_sample_size' => $testSize,
            'metrics' => $metrics,
        ];
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
