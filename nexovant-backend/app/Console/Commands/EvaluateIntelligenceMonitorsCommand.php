<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AutomationExecutionLog;
use App\Services\Automation\IntelligenceMonitoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class EvaluateIntelligenceMonitorsCommand extends Command
{
    protected $signature = 'intelligence:evaluate-monitors {--hours=24 : Lookback window in hours}';
    protected $description = 'Evaluate ingested intelligence across active monitoring rules, signals, and early warnings';

    public function handle(IntelligenceMonitoringService $monitoringService): int
    {
        $hours = (int) $this->option('hours');
        $lockKey = "command:lock:intelligence:evaluate-monitors";
        $lock = Cache::lock($lockKey, 300);

        if (!$lock->get()) {
            $this->warn("Command intelligence:evaluate-monitors is already running. Skipping execution.");
            return self::SUCCESS;
        }

        $log = AutomationExecutionLog::startExecution('intelligence:evaluate-monitors', 'everyFifteenMinutes');
        $this->info("Scanning intelligence ingested within the last {$hours} hours...");

        try {
            $processed = $monitoringService->runScheduledMonitorScan($hours);

            $log->completeSuccess(
                processed: $processed,
                failures: 0,
                skipped: 0,
                metrics: [
                    'lookback_hours' => $hours,
                ]
            );

            $this->info("Intelligence monitoring evaluation finished. Processed {$processed} articles.");
            return self::SUCCESS;
        } catch (Throwable $e) {
            $log->failExecution($e->getMessage(), ['lookback_hours' => $hours]);
            $this->error("Failed intelligence monitoring evaluation: " . $e->getMessage());
            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
