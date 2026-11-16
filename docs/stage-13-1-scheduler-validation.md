# Nexovant — Stage 13.1 Scheduler Validation & Concurrency Protection

## 1. Scheduler Topology & Configuration

In Laravel 11, console routes and scheduled tasks are registered in `routes/console.php`. Stage 13.1 registers:

```php
// Scheduled Ingestion & Automation Pipeline
Schedule::command('nexovant:ingest-feeds')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('intelligence:evaluate-monitors')->everyFifteenMinutes()->withoutOverlapping(30)->onOneServer();
Schedule::command('discovery:evaluate-saved-searches')->hourly()->withoutOverlapping(60)->onOneServer();
```

### Schedule Specifications

| Command | Cadence | Concurrency Lock | Multi-Instance Protection | Target Objective |
|---|---|---|---|---|
| `intelligence:evaluate-monitors` | Every 15 minutes (`*/15 * * * *`) | `withoutOverlapping(30)` | `onOneServer()` | Scans recently ingested articles, evaluates signals, triggers anomaly detectors, updates early warnings, and records evaluations. |
| `discovery:evaluate-saved-searches` | Hourly (`0 * * * *`) | `withoutOverlapping(60)` | `onOneServer()` | Evaluates active persistent saved searches against articles published in the last 24h and dispatches notifications. |
| `nexovant:ingest-feeds` | Every 15 minutes | `withoutOverlapping()` | Standard | Discovers RSS/Atom wire sources and initiates sequential processing. |

---

## 2. Distributed Locking & Multi-Instance Safety

In cloud or clustered environments running multiple web workers or containerized instances, running background evaluation commands concurrently risks duplicate notification dispatches, database race conditions, and CPU contention.

Stage 13.1 implements double-layer protection:

1. **Framework Level (`onOneServer` & `withoutOverlapping`)**:
   - `onOneServer()` utilizes the default Redis/database cache store to elect a single worker node for each invocation.
   - `withoutOverlapping($expiresAtMinutes)` holds a persistent cache mutex ensuring a slow or stalled execution does not cause overlapping process spawns.

2. **Artisan Command Level (`Cache::lock`)**:
   - `EvaluateIntelligenceMonitorsCommand` acquires `Cache::lock('command:lock:intelligence:evaluate-monitors', 300)`.
   - `EvaluateSavedSearchesCommand` acquires `Cache::lock("command:lock:discovery:evaluate-saved-searches:{$cadence}", 300)`.
   - If the lock cannot be acquired immediately, the process emits a warning and exits cleanly (`return self::SUCCESS;`), recording the skip rather than crashing or queueing duplicate background workers.

---

## 3. Automation Execution Logging (`automation_execution_logs`)

Every execution of a scheduled command is tracked in the database:

```php
$log = AutomationExecutionLog::startExecution('intelligence:evaluate-monitors', 'everyFifteenMinutes');

try {
    $processed = $monitoringService->runScheduledMonitorScan($hours);
    $log->completeSuccess(
        processed: $processed,
        failures: 0,
        skipped: 0,
        metrics: ['lookback_hours' => $hours]
    );
} catch (Throwable $e) {
    $log->failExecution($e->getMessage(), ['lookback_hours' => $hours]);
    throw $e;
}
```

The resulting telemetry enables platform engineers to monitor:
- Command name and execution frequency.
- Execution status (`running`, `success`, `failed`, `skipped`).
- Exact execution duration in milliseconds (`duration_ms`).
- Volume of processed items and failure rates.
- Hostname of the execution node.
