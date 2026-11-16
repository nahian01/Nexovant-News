# Nexovant — Stage 13.1 Real Ingestion Pipeline Handshake

## 1. Overview

In Stage 13, signal detection and early-warning evaluation operated either through scheduled cron batch sweeps or manual test invocations. 

Stage 13.1 connects the **real ingestion pipeline directly to the intelligence and surveillance engine** via an asynchronous queued job: `App\Jobs\ProcessIngestedArticleIntelligenceJob`.

---

## 2. Ingestion-to-Intelligence Architecture

```
[RSS / Atom Ingestion]
        │
        ▼
[FeedCollectorService]
        │
        ▼
[ContentExtractor & Deduplication]
        │
        ▼
[StoryClusteringService]
        │
        ▼
[IntelligencePipelineCoordinator::routeEditorialArticle]
        │
        ├── Persist Article Record & Revisions
        ├── Extract & Corroborate Facts
        │
        ▼
[ProcessIngestedArticleIntelligenceJob::dispatch($article->id)]
        │
        ▼ (Queue: default, Async Processing)
[ProcessIngestedArticleIntelligenceJob::handle]
        │
        ├── 1. Acquire Idempotency Lock: "job:lock:article_intelligence:{$articleId}"
        ├── 2. IntelligenceMonitoringService::evaluateIngestedArticle
        │       ├── IntelligenceSignalDetectionService (Rule Match + Corroboration)
        │       ├── AnomalyDetectionService (Reporting Frequency Surge)
        │       ├── EventCorrelationService (Correlations, Causation = false)
        │       ├── EarlyWarningService (9-Point Transparent Assessment)
        │       ├── IntelligenceImpactAssessmentService (8 Dimension Impact)
        │       └── IntelligenceAlertService (NotificationEngine Dispatch)
        │
        ├── 3. SavedSearchService::evaluateItemAndAlert (Subscriber Alerts)
        └── 4. Release Lock & Log Operational Metrics
```

---

## 3. Reliability & Failure Isolation Specifications

The queued job conforms to high-availability production requirements:

- **Queue Contract**: Implements `Illuminate\Contracts\Queue\ShouldQueue`.
- **Max Attempts**: `public int $tries = 3;`
- **Timeout Limit**: `public int $timeout = 120;` seconds.
- **Exponential Backoff**: `public array $backoff = [10, 30, 60];` seconds.
- **Race Condition Prevention**: Employs atomic cache lock `Cache::lock("job:lock:article_intelligence:{$this->articleId}", 120)` to ensure concurrent queue workers never process the same article simultaneously.
- **Graceful Failure Isolation**: Unhandled exceptions are logged with full stack traces (`Log::error`), and `failed(Throwable $exception)` records permanent failures without taking down the queue daemon.
