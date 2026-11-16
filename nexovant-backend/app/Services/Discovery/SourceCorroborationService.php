<?php

declare(strict_types=1);

namespace App\Services\Discovery;

use App\Models\Article;
use App\Models\ClaimEvidence;
use App\Models\DiscoveredStory;
use App\Models\ExtractedFact;
use App\Models\NewsSource;
use App\Models\StoryCluster;
use Illuminate\Support\Collection;

class SourceCorroborationService
{
    /**
     * Build comprehensive source corroboration & transparency breakdown for an article, cluster, or claim.
     *
     * @return array<string, mixed>
     */
    public function getCorroborationReport(string $itemId, string $itemType = 'article'): array
    {
        $primarySource = null;
        $title = '';
        $publishedAt = null;
        $supportingSources = [];
        $independentCorroborators = [];
        $syndicatedReprints = [];
        $contradictoryReports = [];
        $extractedClaims = [];
        $overallStatus = 'unverified';
        $credibilityScore = 80;

        if ($itemType === 'article') {
            $article = Article::with(['source', 'cluster.stories.source'])->find($itemId);
            if ($article) {
                $title = $article->title;
                $publishedAt = $article->published_at?->toIso8601String();
                $primarySource = [
                    'id' => $article->source?->id,
                    'name' => $article->source?->name ?? 'Primary Publisher',
                    'domain' => $article->source?->domain,
                    'trust_tier' => $article->source?->trust_tier ?? 'reputable_secondary',
                    'credibility_score' => $article->source?->credibility_score ?? 85,
                    'is_primary' => true,
                ];
                $credibilityScore = $article->source?->credibility_score ?? 85;

                // Inspect cluster stories if available
                if ($article->cluster) {
                    $clusterStories = $article->cluster->stories()->with('source')->get();
                    foreach ($clusterStories as $story) {
                        if ($story->id === $article->id || !$story->source) {
                            continue;
                        }

                        $isSyndicated = $this->isSyndicatedText(
                            $article->content_normalized ?? $article->title,
                            $story->content_normalized ?? $story->title
                        );

                        $sourceData = [
                            'story_id' => $story->id,
                            'title' => $story->title,
                            'source_name' => $story->source->name,
                            'domain' => $story->source->domain,
                            'published_at' => $story->published_at?->toIso8601String(),
                            'trust_tier' => $story->source->trust_tier,
                            'credibility_score' => $story->source->credibility_score,
                            'is_syndicated' => $isSyndicated,
                            'url' => $story->url ?? "/news/{$story->slug}",
                        ];

                        if ($isSyndicated) {
                            $syndicatedReprints[] = $sourceData;
                        } else {
                            $independentCorroborators[] = $sourceData;
                        }
                    }
                }

                // Check extracted facts related to this article / cluster
                $clusterId = $article->cluster?->id ?? StoryCluster::where('article_id', $article->id)->value('id');
                $facts = ExtractedFact::with(['evidences.source', 'source'])
                    ->when($clusterId, fn($q) => $q->where('cluster_id', $clusterId))
                    ->get();
                foreach ($facts as $fact) {
                    $extractedClaims[] = [
                        'claim' => $fact->claim,
                        'verification_status' => $fact->verification_status,
                        'confidence_score' => $fact->confidence_score,
                    ];

                    foreach ($fact->evidences as $evidence) {
                        $evSource = [
                            'source_name' => $evidence->source?->name ?? 'External Source',
                            'evidence_text' => $evidence->evidence_text,
                            'stance' => $evidence->stance,
                            'published_at' => $evidence->published_at?->toIso8601String(),
                            'is_syndicated' => (bool) $evidence->is_syndicated_reprint,
                            'url' => $evidence->source_url,
                        ];

                        if ($evidence->stance === 'contradicts') {
                            $contradictoryReports[] = $evSource;
                        } elseif ($evidence->is_syndicated_reprint) {
                            $syndicatedReprints[] = $evSource;
                        } else {
                            $supportingSources[] = $evSource;
                            $independentCorroborators[] = $evSource;
                        }
                    }
                }

                $overallStatus = count($contradictoryReports) > 0 ? 'disputed' : ((count($independentCorroborators) > 0 || count($supportingSources) > 0) ? 'verified' : 'single_source');
            }
        } elseif ($itemType === 'cluster' || $itemType === 'story') {
            $cluster = StoryCluster::with('stories.source')->find($itemId);
            if ($cluster) {
                $title = $cluster->canonical_title ?? $cluster->title ?? 'Intelligence Story Cluster';
                $stories = $cluster->stories;
                $firstStory = $stories->first();

                if ($firstStory && $firstStory->source) {
                    $primarySource = [
                        'id' => $firstStory->source->id,
                        'name' => $firstStory->source->name,
                        'domain' => $firstStory->source->domain,
                        'trust_tier' => $firstStory->source->trust_tier,
                        'credibility_score' => $firstStory->source->credibility_score,
                        'is_primary' => true,
                    ];
                }

                foreach ($stories as $idx => $story) {
                    if ($idx === 0) continue;
                    $isSyndicated = $this->isSyndicatedText(
                        $firstStory?->content_normalized ?? '',
                        $story->content_normalized ?? ''
                    );

                    $item = [
                        'story_id' => $story->id,
                        'title' => $story->title,
                        'source_name' => $story->source?->name ?? 'Affiliated Outlet',
                        'domain' => $story->source?->domain,
                        'published_at' => $story->published_at?->toIso8601String(),
                        'credibility_score' => $story->source?->credibility_score ?? 80,
                        'is_syndicated' => $isSyndicated,
                    ];

                    if ($isSyndicated) {
                        $syndicatedReprints[] = $item;
                    } else {
                        $independentCorroborators[] = $item;
                    }
                }

                $overallStatus = count($independentCorroborators) >= 2 ? 'verified' : 'reported_reliable';
            }
        }

        // Deduplicate independent sources count so syndications never inflate corroboration
        $independentSourceCount = count(array_unique(array_column($independentCorroborators, 'source_name')));

        return [
            'item_id' => $itemId,
            'item_type' => $itemType,
            'title' => $title,
            'published_at' => $publishedAt,
            'primary_source' => $primarySource,
            'independent_corroborators' => $independentCorroborators,
            'independent_sources_count' => $independentSourceCount,
            'syndicated_reprints' => $syndicatedReprints,
            'syndicated_count' => count($syndicatedReprints),
            'contradictory_reports' => $contradictoryReports,
            'contradiction_count' => count($contradictoryReports),
            'extracted_claims' => $extractedClaims,
            'verification_status' => $overallStatus,
            'credibility_score' => $credibilityScore,
        ];
    }

    /**
     * Compare side-by-side reporting across multiple sources covering the same event.
     *
     * @param array<int, string> $articleIds
     * @return array<string, mixed>
     */
    public function getSourceComparison(array $articleIds): array
    {
        $articles = Article::with(['source', 'categories'])->whereIn('id', $articleIds)->get();

        $comparison = [];
        foreach ($articles as $article) {
            $comparison[] = [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'source' => [
                    'name' => $article->source?->name ?? 'Publisher',
                    'domain' => $article->source?->domain,
                    'trust_tier' => $article->source?->trust_tier ?? 'reputable_secondary',
                    'credibility_score' => $article->source?->credibility_score ?? 80,
                ],
                'published_at' => $article->published_at?->toIso8601String(),
                'framing_summary' => $article->summary ?? substr(strip_tags($article->content_normalized ?? ''), 0, 300),
                'categories' => $article->categories->pluck('name')->all(),
                'stance' => 'neutral',
            ];
        }

        return [
            'comparison_count' => count($comparison),
            'articles' => $comparison,
        ];
    }

    /**
     * Calculate similarity to identify syndicated wire reprints (>85% text overlap).
     */
    protected function isSyndicatedText(string $textA, string $textB): bool
    {
        if (empty($textA) || empty($textB)) {
            return false;
        }

        similar_text(substr($textA, 0, 400), substr($textB, 0, 400), $percent);
        return $percent >= 85.0;
    }
}
