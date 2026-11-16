<?php

declare(strict_types=1);

namespace App\Services\SourceNetwork;

use App\Models\Article;
use App\Models\NewsSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SourceDiversityService
{
    /**
     * Detect if an article is a syndicated wire reprint and identify the original wire service.
     *
     * @return array{is_syndicated: bool, wire_network: ?string, canonical_source: ?string, similarity_hash: ?string}
     */
    public function detectSyndication(Article $article): array
    {
        $textToAnalyze = strtolower(($article->title ?? '') . ' ' . ($article->content ?? '') . ' ' . ($article->author ?? ''));

        $wirePatterns = [
            'Reuters' => ['/reuters/i', '/\(reuters\)/i', '/reporting by .*? reuters/i'],
            'Associated Press' => ['/associated press/i', '/\(ap\)/i', '/by the associated press/i'],
            'Agence France-Presse' => ['/agence france-presse/i', '/\(afp\)/i'],
            'Bloomberg' => ['/bloomberg news/i', '/\(bloomberg\)/i'],
            'Bangladesh Sangbad Sangstha' => ['/bangladesh sangbad sangstha/i', '/\(bss\)/i'],
            'United News of Bangladesh' => ['/united news of bangladesh/i', '/\(unb\)/i'],
            'PR Newswire' => ['/pr newswire/i', '/prnewswire/i'],
            'Business Wire' => ['/business wire/i', '/businesswire/i'],
            'GlobeNewswire' => ['/globenewswire/i'],
        ];

        foreach ($wirePatterns as $network => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $textToAnalyze)) {
                    return [
                        'is_syndicated' => true,
                        'wire_network' => $network,
                        'canonical_source' => $network,
                        'similarity_hash' => md5(strtolower(trim($article->title))),
                    ];
                }
            }
        }

        // Check if title or content hash matches another earlier article from a different source
        $titleHash = md5(strtolower(trim($article->title)));
        $earlierMatch = Article::where('title', $article->title)
            ->where('id', '!=', $article->id)
            ->where('source_id', '!=', $article->source_id)
            ->first();

        if ($earlierMatch) {
            return [
                'is_syndicated' => true,
                'wire_network' => 'Syndicated Partner / Wire',
                'canonical_source' => $earlierMatch->source?->name ?? 'Original Publisher',
                'similarity_hash' => $titleHash,
            ];
        }

        return [
            'is_syndicated' => false,
            'wire_network' => null,
            'canonical_source' => $article->source?->name,
            'similarity_hash' => $titleHash,
        ];
    }

    /**
     * Calculate source independence and diversity metrics across a collection of articles.
     *
     * @param Collection<int, Article>|array<int, Article> $articles
     * @return array{
     *     raw_article_count: int,
     *     independent_publisher_count: int,
     *     primary_source_count: int,
     *     syndicated_count: int,
     *     diversity_index: float,
     *     unique_publishers: array<int, string>,
     *     wire_networks: array<int, string>
     * }
     */
    public function calculateIndependenceMetrics(Collection|array $articles): array
    {
        $rawCount = count($articles);
        if ($rawCount === 0) {
            return [
                'raw_article_count' => 0,
                'independent_publisher_count' => 0,
                'primary_source_count' => 0,
                'syndicated_count' => 0,
                'diversity_index' => 0.0,
                'unique_publishers' => [],
                'wire_networks' => [],
            ];
        }

        $uniqueDomains = [];
        $wireNetworks = [];
        $syndicatedCount = 0;
        $primarySourceCount = 0;

        foreach ($articles as $article) {
            $source = $article->source;
            $domain = $source?->canonical_domain ?: ($source?->name ?: 'unknown');
            $uniqueDomains[$domain] = true;

            $isPrimary = $source && in_array($source->source_type, ['regulatory', 'government', 'academic', 'academic_repository', 'official_disclosure'], true);
            if ($isPrimary) {
                $primarySourceCount++;
            }

            $syndicationInfo = $this->detectSyndication($article);
            if ($syndicationInfo['is_syndicated']) {
                $syndicatedCount++;
                if ($syndicationInfo['wire_network']) {
                    $wireNetworks[$syndicationInfo['wire_network']] = true;
                }
            }
        }

        $independentCount = count($uniqueDomains);
        // Diversity index is independent publishers relative to total, with bonus for primary sources
        $ratio = $rawCount > 0 ? ($independentCount / $rawCount) : 1.0;
        $primaryBonus = $rawCount > 0 ? ($primarySourceCount / $rawCount) * 0.2 : 0;
        $diversityIndex = round(min(1.0, $ratio + $primaryBonus), 2);

        return [
            'raw_article_count' => $rawCount,
            'independent_publisher_count' => $independentCount,
            'primary_source_count' => $primarySourceCount,
            'syndicated_count' => $syndicatedCount,
            'diversity_index' => $diversityIndex,
            'unique_publishers' => array_keys($uniqueDomains),
            'wire_networks' => array_keys($wireNetworks),
        ];
    }

    /**
     * System-wide source diversity metrics for Admin dashboard.
     *
     * @return array<string, mixed>
     */
    public function getSystemDiversityMetrics(): array
    {
        $totalSources = NewsSource::count();
        $uniquePublishers = NewsSource::distinct('canonical_domain')->count('canonical_domain');
        $primarySources = NewsSource::whereIn('source_type', ['regulatory', 'government', 'academic', 'academic_repository', 'official_disclosure'])->count();

        $byContinent = NewsSource::select('continent', DB::raw('count(*) as count'))
            ->groupBy('continent')
            ->pluck('count', 'continent')
            ->toArray();

        $bySourceType = NewsSource::select('source_type', DB::raw('count(*) as count'))
            ->groupBy('source_type')
            ->pluck('count', 'source_type')
            ->toArray();

        $totalArticles = Article::count();
        $sampleArticles = Article::with('source')->orderByDesc('id')->limit(100)->get();
        $sampleMetrics = $this->calculateIndependenceMetrics($sampleArticles);

        return [
            'total_sources' => $totalSources,
            'unique_canonical_domains' => $uniquePublishers,
            'primary_source_ratio' => $totalSources > 0 ? round(($primarySources / $totalSources) * 100, 1) : 0,
            'primary_sources_count' => $primarySources,
            'diversity_by_continent' => $byContinent,
            'diversity_by_source_type' => $bySourceType,
            'sample_recent_diversity' => [
                'sample_size' => $sampleMetrics['raw_article_count'],
                'independent_publishers' => $sampleMetrics['independent_publisher_count'],
                'syndicated_articles' => $sampleMetrics['syndicated_count'],
                'diversity_index' => $sampleMetrics['diversity_index'],
                'active_wire_networks' => $sampleMetrics['wire_networks'],
            ],
        ];
    }
}
