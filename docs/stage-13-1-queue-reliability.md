# Nexovant — Stage 13.1 Queue Reliability, Retry Policies & Worker Recovery

## 1. Queue Architecture & Contracts

Nexovant leverages Laravel's database/Redis queue infrastructure to run high-throughput intelligence workflows asynchronously without impacting web response latencies.

All automation jobs implement:
- `Illuminate\Contracts\Queue\ShouldQueue`
- Traits: `Dispatchable`, `InteractsWithQueue`, `Queueable`, `SerializesModels`

---

## 2. Retry Policies & Timeout Specifications

Every queued automation job enforces explicit timeout, retry, and backoff attributes:

```php
namespace App\Jobs;

class ProcessIngestedArticleIntelligenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum retry attempts before permanent failure.
     */
    public int $tries = 3;

    /**
     * Timeout in seconds before job process is killed.
     */
    public int $timeout = 120;

    /**
     * Staggered exponential backoff intervals in seconds.
     */
    public array $backoff = [10, 30, 60];
}
```

### Strategy Justification
- **`$tries = 3`**: Protects against transient database lock wait timeouts, network hiccups, or temporary cache outages while preventing infinite poison-pill loops.
- **`$timeout = 120`**: Ensures that heavy AI extraction or multi-source corroboration processes cannot hang workers indefinitely.
- **`$backoff = [10, 30, 60]`**: Backoff pauses allow downstream databases or network APIs time to recover before retrying.

---

## 3. Worker Crash Recovery & Interruption Handling

1. **Idempotency Leases**: The job acquires an atomic cache lock with a 120-second lease:
   ```php
   $lock = Cache::lock("job:lock:article_intelligence:{$this->articleId}", 120);
   ```
   If a worker daemon is killed (`SIGKILL`, OOM, or server reboot) mid-execution, the cache lock expires automatically after 120 seconds, allowing the next retry attempt to pick up the item safely.

2. **Permanent Failure Hook (`failed`)**:
   ```php
   public function failed(Throwable $exception): void
   {
       Log::critical("ProcessIngestedArticleIntelligenceJob failed permanently after {$this->tries} attempts for article [{$this->articleId}]: {$exception->getMessage()}");
   }
   ```
   Unrecoverable errors are logged directly to the error log, recorded in `failed_jobs`, and reflected in the Surveillance Control Room telemetry.
