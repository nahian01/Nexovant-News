<?php

declare(strict_types=1);

namespace App\Services\SourceNetwork;

use App\Models\NewsSource;
use App\Models\SourceHealthLog;
use App\Services\Ingestion\Connectors\SourceConnectorManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SourceHealthService
{
    public function __construct(
        protected SourceConnectorManager $connectorManager
    ) {}

    /**
     * Perform a live health check on a specific source and record health log.
     */
    public function checkSourceHealth(NewsSource $source): SourceHealthLog
    {
        $startTime = hrtime(true);
        $status = 'healthy';
        $httpStatus = null;
        $errorMessage = null;
        $itemsParsed = 0;
        $metadata = [];

        if ($source->editorial_status === 'temporarily_suspended' || $source->editorial_status === 'rejected' || $source->editorial_status === 'archived') {
            $latencyMs = 0;
            $status = 'suspended';
            $errorMessage = "Source is not in active editorial status ({$source->editorial_status}).";
        } else {
            try {
                $testResult = $this->connectorManager->testSource($source);
                $latencyMs = $testResult['latency_ms'] ?? (int) round((hrtime(true) - $startTime) / 1e6);
                $httpStatus = $testResult['http_status'] ?? ($testResult['success'] ? 200 : null);
                $itemsParsed = count($testResult['sample_items'] ?? []);

                if (!$testResult['success']) {
                    $errorMessage = $testResult['message'] ?? 'Connection probe failed.';
                    if (!$httpStatus && preg_match('/HTTP\s+(\d{3})/', (string) $errorMessage, $m)) {
                        $httpStatus = (int) $m[1];
                    }
                    $httpStatus = $httpStatus ?: 500;
                    $status = $this->evaluateFailureStatus($httpStatus, $errorMessage);
                } else {
                    if ($itemsParsed === 0 && !in_array($source->source_type, ['regulatory', 'academic'])) {
                        $status = 'format_changed';
                        $errorMessage = "Zero items extracted. Feed structure or schema may have changed.";
                    } elseif ($latencyMs > 3500) {
                        $status = 'degraded';
                        $errorMessage = "Latency is high ({$latencyMs}ms).";
                    } else {
                        $status = 'healthy';
                    }
                }

                $metadata['sample_count'] = $itemsParsed;
                $metadata['attribution'] = $testResult['attribution'] ?? null;
            } catch (Throwable $e) {
                $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);
                $errorMessage = $e->getMessage();
                $httpStatus = (int) ($e->getCode() ?: 500);
                $status = $this->evaluateFailureStatus($httpStatus, $errorMessage);
            }
        }

        // Determine consecutive failures
        $consecutiveFailures = ($status === 'healthy' || $status === 'degraded') ? 0 : ($source->consecutive_failures + 1);

        // Update news source record
        $source->update([
            'health_status' => $status,
            'last_health_check_at' => now(),
            'last_response_time_ms' => $latencyMs,
            'last_http_status' => $httpStatus,
            'consecutive_failures' => $consecutiveFailures,
            'last_error_message' => $errorMessage,
        ]);

        // Record health log
        return SourceHealthLog::create([
            'source_id' => $source->id,
            'status' => $status,
            'response_time_ms' => $latencyMs,
            'http_status' => $httpStatus,
            'error_message' => $errorMessage,
            'items_parsed' => $itemsParsed,
            'metadata' => $metadata,
            'checked_at' => now(),
        ]);
    }

    /**
     * Evaluate the specific status string based on HTTP status or error.
     */
    public function evaluateFailureStatus(?int $httpStatus, ?string $errorMessage): string
    {
        if ($httpStatus === 401 || $httpStatus === 403 || str_contains(strtolower($errorMessage ?? ''), 'unauthorized') || str_contains(strtolower($errorMessage ?? ''), 'forbidden')) {
            return 'auth_required';
        }

        if ($httpStatus === 429 || str_contains(strtolower($errorMessage ?? ''), 'rate limit') || str_contains(strtolower($errorMessage ?? ''), 'too many requests')) {
            return 'rate_limited';
        }

        if (str_contains(strtolower($errorMessage ?? ''), 'xml') || str_contains(strtolower($errorMessage ?? ''), 'json') || str_contains(strtolower($errorMessage ?? ''), 'schema') || str_contains(strtolower($errorMessage ?? ''), 'format')) {
            return 'format_changed';
        }

        return 'unavailable';
    }

    /**
     * Run health checks across all active sources.
     *
     * @return array<string, mixed>
     */
    public function checkAllSources(?string $sourceType = null): array
    {
        $query = NewsSource::where('is_active', true);
        if ($sourceType) {
            $query->where('source_type', $sourceType);
        }

        $sources = $query->get();
        $results = [];

        foreach ($sources as $source) {
            $log = $this->checkSourceHealth($source);
            $results[] = [
                'source_id' => $source->id,
                'name' => $source->name,
                'status' => $log->status,
                'latency_ms' => $log->response_time_ms,
                'items_parsed' => $log->items_parsed,
            ];
        }

        return [
            'total_checked' => count($results),
            'results' => $results,
        ];
    }

    /**
     * Compile diagnostic overview of source health across the entire network.
     *
     * @return array<string, mixed>
     */
    public function getHealthOverview(): array
    {
        $totalSources = NewsSource::count();
        $activeSources = NewsSource::where('is_active', true)->count();

        $statusCounts = NewsSource::select('health_status', DB::raw('count(*) as count'))
            ->groupBy('health_status')
            ->pluck('count', 'health_status')
            ->toArray();

        $avgLatency = (int) round((float) (NewsSource::whereNotNull('last_response_time_ms')->avg('last_response_time_ms') ?? 0));

        $healthyCount = ($statusCounts['healthy'] ?? 0);
        $availabilityRate = $activeSources > 0 ? round(($healthyCount / $activeSources) * 100, 1) : 100.0;

        $recentIncidents = SourceHealthLog::with('source:id,name,publisher_name,source_type')
            ->whereIn('status', ['unavailable', 'degraded', 'auth_required', 'rate_limited', 'format_changed'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return [
            'total_sources' => $totalSources,
            'active_sources' => $activeSources,
            'availability_rate' => $availabilityRate,
            'average_latency_ms' => $avgLatency,
            'status_breakdown' => [
                'healthy' => $statusCounts['healthy'] ?? 0,
                'degraded' => $statusCounts['degraded'] ?? 0,
                'unavailable' => $statusCounts['unavailable'] ?? 0,
                'auth_required' => $statusCounts['auth_required'] ?? 0,
                'rate_limited' => $statusCounts['rate_limited'] ?? 0,
                'format_changed' => $statusCounts['format_changed'] ?? 0,
                'suspended' => $statusCounts['suspended'] ?? 0,
                'pending' => $statusCounts['pending'] ?? 0,
            ],
            'recent_incidents' => $recentIncidents,
        ];
    }
}
