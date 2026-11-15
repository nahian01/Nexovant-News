<?php

declare(strict_types=1);

namespace App\Services\Research;

use App\Models\DiscoveredStory;
use App\Models\IntelligenceEntity;
use App\Models\IntelligenceTrend;
use App\Models\StoryCluster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrendIntelligenceService
{
    /**
     * Compute and update emerging trend signals across collected stories and story clusters.
     */
    public function detectEmergingTrends(int $lookbackDays = 7): Collection
    {
        // Group recent clusters by headline topics or recurring keywords
        $recentClusters = StoryCluster::where('created_at', '>=', now()->subDays($lookbackDays))
            ->with(['stories.source'])
            ->get();

        $trendBuckets = [];

        foreach ($recentClusters as $cluster) {
            $topicKey = $this->extractTrendKey($cluster->title);
            if (!isset($trendBuckets[$topicKey])) {
                $trendBuckets[$topicKey] = [
                    'name' => ucwords(str_replace('-', ' ', $topicKey)),
                    'clusters' => [],
                    'stories' => [],
                    'sources' => [],
                ];
            }
            $trendBuckets[$topicKey]['clusters'][] = $cluster;

            $clusterStories = $cluster->stories->isNotEmpty()
                ? $cluster->stories
                : DiscoveredStory::where('cluster_id', $cluster->id)->with('source')->get();

            foreach ($clusterStories as $story) {
                $trendBuckets[$topicKey]['stories'][] = $story;
                if ($story->source) {
                    $trendBuckets[$topicKey]['sources'][$story->source->id] = $story->source;
                }
            }
        }

        $detectedTrends = collect();

        foreach ($trendBuckets as $key => $bucket) {
            $storyCount = count($bucket['stories']);
            if ($storyCount < 1) {
                continue;
            }

            $sourceCount = count($bucket['sources']);

            // 1. Volume Score (0 - 100)
            $volumeScore = min(100.0, ($storyCount / 10.0) * 100.0);

            // 2. Growth Rate Velocity Score (0 - 100)
            // Measure stories in last 24h vs prior days
            $last24hCount = count(array_filter($bucket['stories'], fn($s) => $s->created_at >= now()->subDay()));
            $growthVelocity = ($storyCount > 0) ? ($last24hCount / $storyCount) * 100.0 : 0.0;

            // 3. Source Diversity Score (0 - 100)
            // Ratio of independent sources to total stories
            $sourceDiversity = min(100.0, ($sourceCount / max(1, $storyCount)) * 100.0);

            // 4. Source Credibility Score (0 - 100)
            $avgCredibility = 75.0;
            if (!empty($bucket['sources'])) {
                $credSum = array_sum(array_map(fn($s) => (float) $s->credibility_score, $bucket['sources']));
                $avgCredibility = $credSum / count($bucket['sources']);
            }

            // 5. Entity Relevance Score (0 - 100)
            $entityRelevance = min(100.0, count($bucket['clusters']) * 35.0);

            // Composite Trend Score Formula
            $compositeScore = round(
                ($volumeScore * 0.25) +
                ($growthVelocity * 0.30) +
                ($sourceDiversity * 0.20) +
                ($avgCredibility * 0.15) +
                ($entityRelevance * 0.10),
                2
            );

            // Status based on velocity and volume
            $status = match (true) {
                $compositeScore >= 80.0 => 'peak',
                $compositeScore >= 60.0 => 'accelerating',
                $compositeScore >= 40.0 => 'emerging',
                default => 'maturing',
            };

            $trend = IntelligenceTrend::updateOrCreate(
                ['slug' => 'trend-' . $key],
                [
                    'name' => $bucket['name'],
                    'trend_category' => 'technology',
                    'status' => $status,
                    'composite_score' => $compositeScore,
                    'volume_score' => round($volumeScore, 2),
                    'growth_rate_score' => round($growthVelocity, 2),
                    'source_diversity_score' => round($sourceDiversity, 2),
                    'source_credibility_score' => round($avgCredibility, 2),
                    'entity_relevance_score' => round($entityRelevance, 2),
                    'story_count' => $storyCount,
                    'independent_sources_count' => $sourceCount,
                    'last_observed_at' => now(),
                    'metadata' => [
                        'formula_weights' => [
                            'volume' => 0.25,
                            'growth_rate' => 0.30,
                            'source_diversity' => 0.20,
                            'source_credibility' => 0.15,
                            'entity_relevance' => 0.10,
                        ],
                        'lookback_days' => $lookbackDays,
                    ],
                ]
            );

            $detectedTrends->push($trend);
        }

        return $detectedTrends;
    }

    /**
     * Retrieve all active trends with optional filtering.
     *
     * @param array<string, mixed> $filters
     * @return Collection<int, IntelligenceTrend>
     */
    public function getTrendingSignals(array $filters = []): Collection
    {
        $query = IntelligenceTrend::query()->with('entities');

        if (!empty($filters['trend_category'])) {
            $query->where('trend_category', $filters['trend_category']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['min_score'])) {
            $query->where('composite_score', '>=', (float) $filters['min_score']);
        }

        return $query->orderByDesc('composite_score')->get();
    }

    /**
     * Normalize key topics from a title.
     */
    protected function extractTrendKey(string $title): string
    {
        $clean = strtolower(preg_replace('/[^a-zA-Z0-9 ]/', '', $title));
        $words = array_filter(explode(' ', $clean), fn($w) => strlen($w) > 3 && !in_array($w, ['with', 'from', 'that', 'this', 'have', 'were', 'what', 'announced', 'releases', 'reports']));
        $tokens = array_slice(array_values($words), 0, 3);

        return !empty($tokens) ? implode('-', $tokens) : 'tech-intelligence';
    }
}
