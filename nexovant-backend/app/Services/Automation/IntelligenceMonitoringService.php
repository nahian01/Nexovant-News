<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\Article;
use App\Models\EarlyWarning;
use App\Models\IntelligenceSignal;
use App\Models\SubscriberMonitor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class IntelligenceMonitoringService
{
    public function __construct(
        protected IntelligenceSignalDetectionService $signalDetectionService,
        protected AnomalyDetectionService $anomalyDetectionService,
        protected EventCorrelationService $eventCorrelationService,
        protected EarlyWarningService $earlyWarningService,
        protected IntelligenceImpactAssessmentService $impactAssessmentService,
        protected IntelligenceAlertService $alertService
    ) {}

    /**
     * Process newly ingested article through the complete intelligence monitoring lifecycle.
     *
     * @param Article $article
     * @param string|null $tenantId
     * @return array<string, mixed>
     */
    public function evaluateIngestedArticle(Article $article, ?string $tenantId = null): array
    {
        // 1. Signal Detection & Independent Corroboration
        $signal = $this->signalDetectionService->detectSignalFromArticle($article, $tenantId);

        $warning = null;
        $correlationsCount = 0;
        $anomalyDetected = false;
        $alertsDispatched = 0;

        if ($signal) {
            // 2. Anomaly Detection (Reporting Frequency Surge)
            $anomaly = $this->anomalyDetectionService->detectReportingFrequencyAnomaly(
                subjectType: 'topic',
                subjectName: $signal->title,
                signal: $signal
            );
            $anomalyDetected = $anomaly->deviation_score > 2.0;

            // 3. Event Correlation (Strict Correlation != Causation)
            $correlations = $this->eventCorrelationService->correlateArticleEvents($article, $tenantId);
            $correlationsCount = $correlations->count();

            // 4. Early-Warning Assessment
            $warning = $this->earlyWarningService->evaluateSignalForWarning($signal);

            // 5. Impact Assessment
            $this->impactAssessmentService->assessImpact($signal, $warning);

            // 6. Alert Dispatch via NotificationEngine
            if ($warning) {
                $alertsDispatched += $this->alertService->dispatchEarlyWarningAlert($warning);
            } elseif (in_array($signal->severity, ['high', 'critical'], true)) {
                $alertsDispatched += $this->alertService->dispatchSignalAlert($signal);
            }
        }

        // 7. Update Subscriber Monitors Matching Article
        $this->evaluateSubscriberMonitorsForArticle($article);

        return [
            'signal_id' => $signal?->id,
            'signal_title' => $signal?->title,
            'severity' => $signal?->severity,
            'warning_level' => $warning?->warning_level,
            'correlations_count' => $correlationsCount,
            'anomaly_detected' => $anomalyDetected,
            'alerts_dispatched' => $alertsDispatched,
        ];
    }

    /**
     * Evaluate active subscriber monitors for an article.
     */
    protected function evaluateSubscriberMonitorsForArticle(Article $article): int
    {
        $matchedCount = 0;
        $monitors = SubscriberMonitor::where('is_active', true)->get();

        $text = strtolower($article->title . ' ' . ($article->content_normalized ?? ''));

        foreach ($monitors as $monitor) {
            $matches = false;
            $keywords = $monitor->keywords ?? [];

            foreach ($keywords as $kw) {
                if (str_contains($text, strtolower((string) $kw))) {
                    $matches = true;
                    break;
                }
            }

            if ($matches) {
                $monitor->match_count += 1;
                $monitor->last_triggered_at = now();
                $monitor->save();
                $matchedCount++;
            }
        }

        return $matchedCount;
    }

    /**
     * Run batch evaluation of active monitors across recent articles.
     */
    public function runScheduledMonitorScan(int $lookbackHours = 24): int
    {
        $articles = Article::where('published_at', '>=', now()->subHours($lookbackHours))
            ->with(['source', 'companies', 'categories'])
            ->get();

        $processed = 0;
        foreach ($articles as $article) {
            $this->evaluateIngestedArticle($article);
            $processed++;
        }

        return $processed;
    }
}
