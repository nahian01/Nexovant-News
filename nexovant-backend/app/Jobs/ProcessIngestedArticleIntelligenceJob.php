<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Article;
use App\Services\Automation\IntelligenceMonitoringService;
use App\Services\Discovery\SavedSearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessIngestedArticleIntelligenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $articleId,
        public ?string $tenantId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        IntelligenceMonitoringService $monitoringService,
        SavedSearchService $savedSearchService
    ): void {
        $article = Article::find($this->articleId);
        if (!$article) {
            Log::warning("ProcessIngestedArticleIntelligenceJob: Article [{$this->articleId}] not found. Skipping.");
            return;
        }

        // Idempotency lock to prevent race conditions during rapid multi-worker ingestion
        $lockKey = "job:lock:article_intelligence:{$this->articleId}";
        $lock = Cache::lock($lockKey, 120);

        if (!$lock->get()) {
            Log::info("ProcessIngestedArticleIntelligenceJob: Ingestion intelligence already processing for article [{$this->articleId}].");
            return;
        }

        try {
            Log::info("Processing ingested article intelligence: [{$article->id}] '{$article->title}'");

            // 1. Evaluate through complete intelligence monitoring & early-warning pipeline
            $evalResult = $monitoringService->evaluateIngestedArticle($article, $this->tenantId);

            // 2. Evaluate persistent subscriber saved searches
            $savedSearchAlerts = $savedSearchService->evaluateItemAndAlert($article, 'article');

            Log::info("Ingested article intelligence completed for [{$article->id}]", [
                'signal_id' => $evalResult['signal_id'] ?? null,
                'severity' => $evalResult['severity'] ?? null,
                'warning_level' => $evalResult['warning_level'] ?? null,
                'alerts_dispatched' => ($evalResult['alerts_dispatched'] ?? 0) + $savedSearchAlerts,
            ]);
        } catch (Throwable $e) {
            Log::error("Failed to process ingested article intelligence for [{$this->articleId}]: {$e->getMessage()}", [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::critical("ProcessIngestedArticleIntelligenceJob failed permanently after {$this->tries} attempts for article [{$this->articleId}]: {$exception->getMessage()}");
    }
}
