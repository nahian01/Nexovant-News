<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\TrendingIntelligenceRadarService;
use Illuminate\Console\Command;

class DetectTrendingRadarCommand extends Command
{
    protected $signature = 'nexovant:detect-trends 
                            {--window= : Filter by time window (24h, 7d, 30d)}
                            {--subject= : Optional custom prediction subject}';

    protected $description = 'Automatically detect trending intelligence radar topics from recent news, clusters, and entity momentum';

    public function handle(TrendingIntelligenceRadarService $radarService): int
    {
        $this->info('[' . now()->toIso8601String() . '] Initiating Trending Intelligence Radar detection...');

        if ($subject = $this->option('subject')) {
            $this->info("Processing custom predictive radar topic for: '{$subject}'...");
            $trend = $radarService->customPredictiveTrend([
                'subject' => $subject,
                'category' => 'technology_adoption',
                'horizon' => '90d',
            ]);
            $this->info("✓ Generated predictive trend: [{$trend->title}] (Score: {$trend->composite_trend_score})");
            return self::SUCCESS;
        }

        $window = $this->option('window');
        $trends = $radarService->detectAndSyncTrends($window);

        $this->info("✓ Successfully synchronized {$trends->count()} trending radar topics:");

        $headers = ['Rank', 'Title', 'Velocity Score', 'Status', 'Cross-Platform'];
        $rows = [];

        foreach ($trends->values() as $idx => $trend) {
            $rows[] = [
                $idx + 1,
                mb_strimwidth($trend->title, 0, 50, '...'),
                "{$trend->composite_trend_score}/100 ({$trend->velocity_delta})",
                $trend->status,
                "{$trend->cross_platform_count} platforms",
            ];
        }

        $this->table($headers, $rows);

        return self::SUCCESS;
    }
}
