<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AnalystReview;
use App\Models\AnomalyObservation;
use App\Models\AutomationExecutionLog;
use App\Models\EarlyWarning;
use App\Models\IntelligenceDetectionRule;
use App\Models\IntelligenceSignal;
use App\Services\Automation\AnalystReviewService;
use App\Services\Automation\IntelligenceMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntelligenceAutomationAdminController
{
    public function __construct(
        protected IntelligenceMonitoringService $monitoringService,
        protected AnalystReviewService $reviewService
    ) {}

    /**
     * Executive Overview KPIs for Automation & Early-Warning Control Room.
     */
    public function overview(Request $request): JsonResponse
    {
        $totalSignals = IntelligenceSignal::count();
        $activeSignals = IntelligenceSignal::whereNotIn('status', ['dismissed', 'resolved'])->count();
        $criticalWarnings = EarlyWarning::whereIn('warning_level', ['critical', 'elevated'])->count();
        $totalWarnings = EarlyWarning::count();
        $pendingReviews = IntelligenceSignal::where('review_status', 'pending')->count();
        $totalAnomalies = AnomalyObservation::count();

        $totalReviews = AnalystReview::count();
        $falsePositives = AnalystReview::where('decision', 'marked_false_positive')->count();
        $falsePositiveRate = $totalReviews > 0 ? round(($falsePositives / (float) $totalReviews) * 100, 1) : 0.0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_signals' => $totalSignals,
                'active_signals' => $activeSignals,
                'total_warnings' => $totalWarnings,
                'critical_or_elevated_warnings' => $criticalWarnings,
                'pending_analyst_reviews' => $pendingReviews,
                'total_anomalies' => $totalAnomalies,
                'false_positive_rate' => $falsePositiveRate,
                'queue_status' => 'healthy',
                'active_detection_rules' => IntelligenceDetectionRule::where('is_active', true)->count(),
            ],
        ]);
    }

    /**
     * Operational Telemetry: Scheduler health, execution history, queue health, latencies.
     */
    public function telemetry(Request $request): JsonResponse
    {
        $lastMonitorRun = AutomationExecutionLog::where('command_name', 'intelligence:evaluate-monitors')
            ->latest('started_at')
            ->first();

        $lastSearchRun = AutomationExecutionLog::where('command_name', 'discovery:evaluate-saved-searches')
            ->latest('started_at')
            ->first();

        $logs = AutomationExecutionLog::latest('started_at')->limit(25)->get();

        $pendingJobs = 0;
        $failedJobs = 0;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('jobs')) {
                $pendingJobs = \Illuminate\Support\Facades\DB::table('jobs')->count();
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('failed_jobs')) {
                $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
            }
        } catch (\Throwable $e) {}

        $executions24h = AutomationExecutionLog::where('started_at', '>=', now()->subHours(24))->count();
        $failures24h = AutomationExecutionLog::where('started_at', '>=', now()->subHours(24))->where('status', 'failed')->count();
        $avgDuration = (int) round((float) (AutomationExecutionLog::whereNotNull('duration_ms')->avg('duration_ms') ?? 0));

        $heartbeatStatus = 'healthy';
        if ($lastMonitorRun && $lastMonitorRun->started_at->lt(now()->subHours(2))) {
            $heartbeatStatus = 'delayed';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'heartbeat' => [
                    'status' => $heartbeatStatus,
                    'last_monitor_eval_at' => $lastMonitorRun?->started_at?->toIso8601String(),
                    'last_saved_search_eval_at' => $lastSearchRun?->started_at?->toIso8601String(),
                    'last_duration_ms' => $lastMonitorRun?->duration_ms,
                ],
                'queue' => [
                    'status' => $failedJobs > 5 ? 'degraded' : 'healthy',
                    'pending_jobs' => $pendingJobs,
                    'failed_jobs' => $failedJobs,
                    'executions_24h' => $executions24h,
                    'failures_24h' => $failures24h,
                    'avg_duration_ms' => $avgDuration,
                ],
                'recent_executions' => $logs,
            ],
        ]);
    }

    /**
     * List detection rules.
     */
    public function rules(Request $request): JsonResponse
    {
        $rules = IntelligenceDetectionRule::latest('updated_at')->get();

        return response()->json([
            'success' => true,
            'data' => $rules,
        ]);
    }

    /**
     * Store new detection rule.
     */
    public function storeRule(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'rule_type' => 'required|string|in:volume_velocity,source_corroboration,anomaly_threshold,early_warning_escalation',
            'category' => 'required|string|max:64',
            'thresholds' => 'required|array',
        ]);

        $rule = IntelligenceDetectionRule::create([
            'name' => (string) $request->input('name'),
            'rule_type' => (string) $request->input('rule_type'),
            'category' => (string) $request->input('category'),
            'thresholds' => $request->input('thresholds'),
            'is_active' => true,
            'created_by_user_id' => $request->user()->id,
            'version' => 1,
            'audit_trail' => [
                [
                    'timestamp' => now()->toIso8601String(),
                    'action' => 'rule_created',
                    'user_id' => $request->user()->id,
                ]
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Intelligence detection rule created.',
            'data' => $rule,
        ], 201);
    }

    /**
     * Update detection rule thresholds with version tracking.
     */
    public function updateRule(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'thresholds' => 'required|array',
            'reason' => 'nullable|string|max:500',
        ]);

        $rule = IntelligenceDetectionRule::findOrFail($id);
        $reason = $request->input('reason', 'Administrative threshold calibration');

        $updated = $this->reviewService->applyRuleAdjustment(
            rule: $rule,
            newThresholds: $request->input('thresholds'),
            analyst: $request->user(),
            reason: $reason
        );

        return response()->json([
            'success' => true,
            'message' => 'Detection rule updated and version incremented.',
            'data' => $updated,
        ]);
    }

    /**
     * Toggle rule active status.
     */
    public function toggleRule(Request $request, string $id): JsonResponse
    {
        $rule = IntelligenceDetectionRule::findOrFail($id);
        $rule->is_active = !$rule->is_active;
        $rule->save();

        return response()->json([
            'success' => true,
            'message' => 'Rule status toggled.',
            'is_active' => $rule->is_active,
        ]);
    }

    /**
     * Trigger on-demand monitoring and intelligence ingestion scan.
     */
    public function triggerScan(Request $request, \App\Services\Ingestion\IntelligencePipelineCoordinator $coordinator): JsonResponse
    {
        $admin = $request->user();
        $hours = (int) $request->input('hours', 24);

        // 1. Ingest from active sources
        $sources = \App\Models\NewsSource::where('is_active', true)->whereNotNull('feed_url')->get();
        $sourcesScanned = 0;
        $itemsFetched = 0;
        $storiesCreated = 0;
        $articlesGenerated = 0;

        foreach ($sources as $source) {
            try {
                $stats = $coordinator->processSource($source, $admin, true);
                $itemsFetched += $stats['items_fetched'];
                $storiesCreated += $stats['stories_created'];
                $articlesGenerated += $stats['articles_generated'];
                $sourcesScanned++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Intelligence automation scan error on [{$source->name}]: " . $e->getMessage());
            }
        }

        // 2. Evaluate monitoring rules & early warnings across articles
        $processed = $this->monitoringService->runScheduledMonitorScan($hours);

        return response()->json([
            'success' => true,
            'message' => "Intelligence scan complete: scanned {$sourcesScanned} sources, fetched {$itemsFetched} items, generated {$articlesGenerated} articles, and evaluated {$processed} articles for early warnings.",
            'processed_count' => $processed,
            'sources_scanned' => $sourcesScanned,
            'items_fetched' => $itemsFetched,
            'stories_created' => $storiesCreated,
            'articles_generated' => $articlesGenerated,
        ]);
    }
}
