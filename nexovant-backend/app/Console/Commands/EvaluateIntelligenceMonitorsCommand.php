<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Automation\IntelligenceMonitoringService;
use Illuminate\Console\Command;

class EvaluateIntelligenceMonitorsCommand extends Command
{
    protected $signature = 'intelligence:evaluate-monitors {--hours=24 : Lookback window in hours}';
    protected $description = 'Evaluate ingested intelligence across active monitoring rules, signals, and early warnings';

    public function handle(IntelligenceMonitoringService $monitoringService): int
    {
        $hours = (int) $this->option('hours');
        $this->info("Scanning intelligence ingested within the last {$hours} hours...");

        $processed = $monitoringService->runScheduledMonitorScan($hours);

        $this->info("Intelligence monitoring evaluation finished. Processed {$processed} articles.");
        return self::SUCCESS;
    }
}
