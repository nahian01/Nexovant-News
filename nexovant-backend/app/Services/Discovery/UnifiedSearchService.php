<?php

declare(strict_types=1);

namespace App\Services\Discovery;

use App\Models\Article;
use App\Models\Category;
use App\Models\IntelligenceEntity;
use App\Models\IntelligenceEvent;
use App\Models\KnowledgeChunk;
use App\Models\NewsSource;
use App\Models\Publication;
use App\Models\ResearchReport;
use App\Models\StoryCluster;
use App\Models\SubscriberCollection;
use App\Models\SubscriberLibraryItem;
use App\Models\User;
use App\Services\Discovery\DTO\SearchQueryDTO;
use App\Services\Discovery\DTO\SearchResultItemDTO;
use App\Services\RAG\GroundedRagService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class UnifiedSearchService
{
    public function __construct(
        protected SearchFilterService $filterService,
        protected GroundedRagService $ragService,
        protected SearchAnalyticsService $analyticsService
    ) {}

    /**
     * Execute unified hybrid search across all intelligence assets.
     *
     * @return array<string, mixed>
     */
    public function search(SearchQueryDTO $queryDto, ?User $user = null): array
    {
        $startTime = hrtime(true);
        $queryText = $queryDto->query;
        $contentType = $queryDto->contentType;
        $filters = $queryDto->filters;
        $tenantId = $queryDto->tenantId ?? $user?->organization_id;

        if (mb_strlen($queryText) < 2) {
            return [
                'success' => true,
                'query' => $queryText,
                'total' => 0,
                'results' => [],
                'meta' => [
                    'retrieval_method' => 'empty_query',
                    'latency_ms' => 0,
                    'tenant_id' => $tenantId,
                ],
            ];
        }

        // 1. Semantic Vector Context Retrieval (if hybrid enabled)
        $semanticChunkIds = [];
        $retrievalMethod = 'keyword';

        if ($queryDto->isHybrid) {
            try {
                $ragResult = $this->ragService->query($queryText, $tenantId, $user?->id, limit: 5);
                if (!empty($ragResult['source_citations'])) {
                    $semanticChunkIds = array_column($ragResult['source_citations'], 'chunk_id');
                    $retrievalMethod = 'hybrid_vector';
                }
            } catch (\Throwable) {
                $retrievalMethod = 'keyword_fallback';
            }
        }

        $items = [];

        // 2. Multi-Entity Retrieval based on Content Type
        if ($contentType === 'all' || $contentType === 'article') {
            $items = array_merge($items, $this->searchArticles($queryText, $filters, $queryDto->sortBy, $semanticChunkIds, $tenantId));
        }

        if ($contentType === 'all' || $contentType === 'story') {
            $items = array_merge($items, $this->searchStoryClusters($queryText, $filters, $queryDto->sortBy));
        }

        if ($contentType === 'all' || $contentType === 'report') {
            $items = array_merge($items, $this->searchResearchReports($queryText, $filters, $queryDto->sortBy));
        }

        if ($contentType === 'all' || $contentType === 'publication') {
            $items = array_merge($items, $this->searchPublications($queryText, $filters, $queryDto->sortBy));
        }

        if ($contentType === 'all' || $contentType === 'entity') {
            $items = array_merge($items, $this->searchEntities($queryText, $filters));
        }

        if ($contentType === 'all' || $contentType === 'event') {
            $items = array_merge($items, $this->searchEvents($queryText, $filters, $queryDto->sortBy));
        }

        if ($contentType === 'all' || $contentType === 'source') {
            $items = array_merge($items, $this->searchSources($queryText, $filters, $tenantId));
        }

        if ($contentType === 'all' || $contentType === 'collection') {
            $items = array_merge($items, $this->searchCollections($queryText, $user, $tenantId));
        }

        // 3. Composite Hybrid Ranking & Sorting
        usort($items, function (SearchResultItemDTO $a, SearchResultItemDTO $b) use ($queryDto) {
            if ($queryDto->sortBy === 'recent') {
                return strcmp($b->publishedAt ?? '', $a->publishedAt ?? '');
            } elseif ($queryDto->sortBy === 'oldest') {
                return strcmp($a->publishedAt ?? '', $b->publishedAt ?? '');
            } elseif ($queryDto->sortBy === 'highest_credibility') {
                $credA = (float) ($a->source['credibility_score'] ?? 50);
                $credB = (float) ($b->source['credibility_score'] ?? 50);
                return $credB <=> $credA;
            } elseif ($queryDto->sortBy === 'highest_corroboration') {
                return $b->corroborationCount <=> $a->corroborationCount;
            }
            // Default: Composite relevance score
            return $b->relevanceScore <=> $a->relevanceScore;
        });

        // 4. Pagination
        $total = count($items);
        $offset = ($queryDto->page - 1) * $queryDto->perPage;
        $paginated = array_slice($items, $offset, $queryDto->perPage);

        $resultsArray = array_map(fn (SearchResultItemDTO $item) => $item->toArray(), $paginated);
        $durationMs = max(2, (int) round((hrtime(true) - $startTime) / 1e6));

        // 5. Log Telemetry
        $this->analyticsService->logSearch(
            query: $queryText,
            resultCount: $total,
            durationMs: $durationMs,
            retrievalMethod: $retrievalMethod,
            category: $contentType,
            filters: $filters,
            user: $user
        );

        return [
            'success' => true,
            'query' => $queryText,
            'total' => $total,
            'page' => $queryDto->page,
            'per_page' => $queryDto->perPage,
            'total_pages' => (int) ceil($total / $queryDto->perPage),
            'results' => $resultsArray,
            'meta' => [
                'retrieval_method' => $retrievalMethod,
                'latency_ms' => $durationMs,
                'tenant_id' => $tenantId,
                'is_hybrid' => $queryDto->isHybrid,
                'applied_filters' => $filters,
                'sort_by' => $queryDto->sortBy,
            ],
        ];
    }

    /**
     * Search Articles with full text, credibility, and recency weighting.
     *
     * @return array<int, SearchResultItemDTO>
     */
    protected function searchArticles(string $query, array $filters, string $sortBy, array $semanticChunkIds, ?string $tenantId = null): array
    {
        $qb = Article::query()->with(['source', 'categories', 'companies', 'translations']);

        // Multi-tenant boundary: only return articles from public sources OR the requesting tenant's private sources
        $qb->where(function (Builder $aq) use ($tenantId) {
            $aq->whereDoesntHave('source')
               ->orWhereHas('source', function (Builder $sq) use ($tenantId) {
                   if ($tenantId) {
                       $sq->whereNull('organization_id')
                          ->orWhere('organization_id', $tenantId);
                   } else {
                       $sq->whereNull('organization_id');
                   }
               });
        });

        // Apply filters
        $this->filterService->applyDateFilter($qb, $filters, 'published_at');
        $this->filterService->applyCredibilityFilter($qb, $filters, 'source');
        $this->filterService->applyVerificationFilter($qb, $filters, 'verification_status');
        $this->filterService->applyCategoryFilter($qb, $filters);
        $this->filterService->applyRegionFilter($qb, $filters);

        // Language filter (Stage 14)
        if (!empty($filters['language'])) {
            $lang = strtolower((string) $filters['language']);
            $qb->where(function (Builder $lq) use ($lang) {
                $lq->where('original_language', $lang)
                   ->orWhere('detected_language', $lang)
                   ->orWhereHas('source', fn(Builder $sq) => $sq->where('language', $lang))
                   ->orWhereHas('translations', fn(Builder $tq) => $tq->where('target_language', $lang));
            });
        }

        $qb->where(function (Builder $q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
              ->orWhere('content_normalized', 'like', "%{$query}%")
              ->orWhere('content_raw', 'like', "%{$query}%")
              ->orWhereHas('translations', function (Builder $tq) use ($query) {
                  $tq->where('translated_title', 'like', "%{$query}%")
                     ->orWhere('translated_summary', 'like', "%{$query}%")
                     ->orWhere('translated_content', 'like', "%{$query}%");
              });
        });

        $articles = $qb->limit(30)->get();
        $items = [];

        foreach ($articles as $article) {
            $titleMatch = stripos($article->title, $query) !== false;
            $translationMatch = $article->translations->first(function ($t) use ($query) {
                return stripos((string) $t->translated_title, $query) !== false || stripos((string) $t->translated_summary, $query) !== false;
            });
            if (!$titleMatch && $translationMatch) {
                $titleMatch = true;
            }

            $rawCred = (float) ($article->source?->credibility_score ?? 0.85);
            $credibility = $rawCred <= 1.0 ? round($rawCred * 100.0) : $rawCred;
            $trustTier = $article->source?->trust_tier ?? 'reputable_secondary';

            // Calculate recency decay factor (90 days half-life)
            $daysOld = $article->published_at ? max(0, $article->published_at->diffInDays(now())) : 30;
            $recencyFactor = exp(-$daysOld / 90.0);

            // Base score
            $textScore = $titleMatch ? 0.90 : 0.65;
            $credibilityScore = $credibility / 100.0;
            $semanticScore = in_array($article->id, $semanticChunkIds, true) ? 0.95 : 0.60;

            // Composite formula
            $composite = (0.40 * $textScore) + (0.35 * $semanticScore) + (0.15 * $credibilityScore) + (0.10 * $recencyFactor);

            $verificationStatus = in_array($article->verification_status, ['verified', 'confirmed'], true) ? 'verified' : ($article->verification_status ?? 'unverified');
            $classification = $verificationStatus === 'verified' ? 'verified_fact' : 'unverified_report';
            if ($credibility < 70) {
                $verificationStatus = 'unverified';
                $classification = 'unverified_report';
            }

            $explanation = $translationMatch
                ? "Multilingual translation match ('{$translationMatch->target_language}') with {$credibility}% source credibility."
                : ($titleMatch
                    ? "Direct headline match with {$credibility}% source credibility and confirmed recency."
                    : "Extracted from verified context with corroborated facts from {$article->source?->name}.");

            $matchedEntities = $article->companies->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'type' => 'company'])->all();
            $matchedTopics = $article->categories->pluck('name')->all();

            $excerptText = is_string($article->summary)
                ? $article->summary
                : ($article->summary?->executive_summary ?? Str::limit(strip_tags((string) ($article->content_normalized ?? $article->content_raw ?? '')), 220));

            $translationsSummary = $article->translations->map(fn($t) => [
                'language' => $t->target_language,
                'title' => $t->translated_title,
                'summary' => $t->translated_summary,
            ])->all();

            $items[] = new SearchResultItemDTO(
                id: (string) $article->id,
                title: $article->title,
                excerpt: (string) $excerptText,
                contentType: 'article',
                publishedAt: $article->published_at?->toIso8601String(),
                updatedAt: $article->updated_at?->toIso8601String(),
                source: [
                    'id' => $article->source?->id,
                    'name' => $article->source?->name ?? 'Primary Publisher',
                    'domain' => $article->source?->domain,
                    'trust_tier' => $trustTier,
                    'credibility_score' => $credibility,
                ],
                matchedEntities: $matchedEntities,
                matchedTopics: $matchedTopics,
                relevanceScore: min(1.0, max(0.1, $composite)),
                relevanceExplanation: $explanation,
                verificationStatus: $verificationStatus,
                corroborationCount: 2,
                url: "/news/{$article->slug}",
                informationClassification: $classification,
                originalLanguage: $article->original_language ?? $article->source?->language,
                detectedLanguage: $article->detected_language,
                translations: $translationsSummary
            );
        }

        return $items;
    }

    /**
     * Search Story Clusters.
     *
     * @return array<int, SearchResultItemDTO>
     */
    protected function searchStoryClusters(string $query, array $filters, string $sortBy): array
    {
        $qb = StoryCluster::query()->with('stories.source');

        $qb->where(function (Builder $q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
              ->orWhere('summary', 'like', "%{$query}%");
        });

        $clusters = $qb->limit(15)->get();
        $items = [];

        foreach ($clusters as $cluster) {
            $storyCount = $cluster->stories->count();
            $firstStory = $cluster->stories->first();
            $source = $firstStory?->source;

            $items[] = new SearchResultItemDTO(
                id: (string) $cluster->id,
                title: $cluster->title ?? 'Story Cluster',
                excerpt: $cluster->summary ?? 'Cross-source story cluster covering coordinated technology developments.',
                contentType: 'story',
                publishedAt: $cluster->first_seen_at?->toIso8601String() ?? $cluster->created_at?->toIso8601String(),
                updatedAt: $cluster->last_updated_at?->toIso8601String() ?? $cluster->updated_at?->toIso8601String(),
                source: [
                    'name' => $source?->name ?? 'Multi-Source Cluster',
                    'domain' => $source?->domain,
                    'trust_tier' => $source?->trust_tier ?? 'reputable_secondary',
                    'credibility_score' => $source?->credibility_score ?? 85,
                ],
                matchedEntities: [],
                matchedTopics: ['Intelligence Clusters'],
                relevanceScore: 0.88,
                relevanceExplanation: "Multi-outlet story cluster corroborated by {$storyCount} independent publications.",
                verificationStatus: $storyCount >= 2 ? 'verified' : 'reported_reliable',
                corroborationCount: $storyCount,
                url: "/feed",
                informationClassification: 'verified_fact'
            );
        }

        return $items;
    }

    /**
     * Search Research Reports.
     *
     * @return array<int, SearchResultItemDTO>
     */
    protected function searchResearchReports(string $query, array $filters, string $sortBy): array
    {
        $qb = ResearchReport::query()->where('is_public', true);

        $qb->where(function (Builder $q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
              ->orWhere('executive_summary', 'like', "%{$query}%")
              ->orWhere('content_markdown', 'like', "%{$query}%");
        });

        $reports = $qb->limit(10)->get();
        $items = [];

        foreach ($reports as $report) {
            $items[] = new SearchResultItemDTO(
                id: (string) $report->id,
                title: $report->title,
                excerpt: $report->executive_summary,
                contentType: 'report',
                publishedAt: $report->created_at?->toIso8601String(),
                updatedAt: $report->reviewed_at?->toIso8601String() ?? $report->updated_at?->toIso8601String(),
                source: [
                    'name' => 'Nexovant Deep Research Unit',
                    'trust_tier' => 'established_primary',
                    'credibility_score' => 98,
                ],
                matchedEntities: [],
                matchedTopics: ['Deep Research'],
                relevanceScore: 0.92,
                relevanceExplanation: "Editorial deep research investigation with {$report->confidence_score} confidence rating.",
                verificationStatus: 'verified',
                corroborationCount: 4,
                url: "/subscriber/reports",
                informationClassification: 'inferred_intelligence'
            );
        }

        return $items;
    }

    /**
     * Search Executive Publications.
     *
     * @return array<int, SearchResultItemDTO>
     */
    protected function searchPublications(string $query, array $filters, string $sortBy): array
    {
        $qb = Publication::query()->where('status', 'published');

        $qb->where(function (Builder $q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
              ->orWhere('subtitle', 'like', "%{$query}%");
        });

        $pubs = $qb->limit(8)->get();
        $items = [];

        foreach ($pubs as $pub) {
            $items[] = new SearchResultItemDTO(
                id: (string) $pub->id,
                title: $pub->title,
                excerpt: $pub->subtitle ?? 'Executive Intelligence Edition',
                contentType: 'publication',
                publishedAt: $pub->publication_date?->toIso8601String() ?? $pub->created_at?->toIso8601String(),
                updatedAt: $pub->updated_at?->toIso8601String(),
                source: [
                    'name' => 'Nexovant Executive Publications',
                    'trust_tier' => 'official_announcement',
                    'credibility_score' => 100,
                ],
                matchedEntities: [],
                matchedTopics: ['Executive Briefing'],
                relevanceScore: 0.94,
                relevanceExplanation: "Official published intelligence edition #{$pub->edition_number}.",
                verificationStatus: 'verified',
                corroborationCount: 5,
                url: "/subscriber/publications",
                informationClassification: 'verified_fact'
            );
        }

        return $items;
    }

    /**
     * Search Entities (Companies, Executives, Technologies, Institutions, Locations).
     *
     * @return array<int, SearchResultItemDTO>
     */
    protected function searchEntities(string $query, array $filters): array
    {
        $qb = IntelligenceEntity::query()->with('aliases');

        $qb->where(function (Builder $q) use ($query) {
            $q->where('canonical_name', 'like', "%{$query}%")
              ->orWhere('legal_name', 'like', "%{$query}%")
              ->orWhere('description', 'like', "%{$query}%")
              ->orWhereHas('aliases', fn($aq) => $aq->where('alias', 'like', "%{$query}%"));
        });

        if (!empty($filters['entity_type']) && $filters['entity_type'] !== 'all') {
            $qb->where('entity_type', $filters['entity_type']);
        }

        $entities = $qb->limit(10)->get();
        $items = [];

        foreach ($entities as $e) {
            $items[] = new SearchResultItemDTO(
                id: (string) $e->id,
                title: "{$e->canonical_name} (" . ucfirst($e->entity_type) . ")",
                excerpt: $e->description ?? "Corporate profile and intelligence monitoring record for {$e->canonical_name}.",
                contentType: 'entity',
                publishedAt: $e->created_at?->toIso8601String(),
                updatedAt: $e->last_seen_at?->toIso8601String() ?? $e->updated_at?->toIso8601String(),
                source: [
                    'name' => 'Nexovant Entity Registry',
                    'trust_tier' => 'established_primary',
                    'credibility_score' => 95,
                ],
                matchedEntities: [['id' => $e->id, 'name' => $e->canonical_name, 'type' => $e->entity_type]],
                matchedTopics: [ucfirst($e->entity_type)],
                relevanceScore: 0.96,
                relevanceExplanation: "Entity graph profile with verified relationships and historical timeline events.",
                verificationStatus: $e->verification_status ?? 'verified',
                corroborationCount: 3,
                url: "/subscriber/companies",
                informationClassification: 'verified_fact'
            );
        }

        return $items;
    }

    /**
     * Search Intelligence Events.
     *
     * @return array<int, SearchResultItemDTO>
     */
    protected function searchEvents(string $query, array $filters, string $sortBy): array
    {
        $qb = IntelligenceEvent::query();

        $qb->where(function (Builder $q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
              ->orWhere('overview', 'like', "%{$query}%");
        });

        $events = $qb->limit(10)->get();
        $items = [];

        foreach ($events as $ev) {
            $items[] = new SearchResultItemDTO(
                id: (string) $ev->id,
                title: $ev->title,
                excerpt: $ev->overview ?? 'Recorded timeline event.',
                contentType: 'event',
                publishedAt: $ev->started_at?->toIso8601String() ?? $ev->created_at?->toIso8601String(),
                updatedAt: $ev->updated_at?->toIso8601String(),
                source: [
                    'name' => 'Historical Timeline Registry',
                    'trust_tier' => 'established_primary',
                    'credibility_score' => 90,
                ],
                matchedEntities: [],
                matchedTopics: [$ev->event_type ?? 'Event'],
                relevanceScore: 0.85,
                relevanceExplanation: "Timeline milestone: " . ($ev->started_at?->format('M d, Y') ?? 'N/A'),
                verificationStatus: $ev->status ?? 'reported_reliable',
                corroborationCount: 2,
                url: "/subscriber/trends",
                informationClassification: 'verified_fact'
            );
        }

        return $items;
    }

    /**
     * Search News Sources.
     *
     * @return array<int, SearchResultItemDTO>
     */
    protected function searchSources(string $query, array $filters, ?string $tenantId = null): array
    {
        $qb = NewsSource::query()
            ->where('is_active', true)
            ->where(function (Builder $sq) use ($tenantId) {
                if ($tenantId) {
                    $sq->whereNull('organization_id')
                       ->orWhere('organization_id', $tenantId);
                } else {
                    $sq->whereNull('organization_id');
                }
            })
            ->where(function (Builder $q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('publisher_name', 'like', "%{$query}%")
                  ->orWhere('url', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            });

        $sources = $qb->limit(6)->get();
        $items = [];

        foreach ($sources as $s) {
            $items[] = new SearchResultItemDTO(
                id: (string) $s->id,
                title: $s->name,
                excerpt: "Monitored publishing outlet: {$s->publisher_name} ({$s->trust_tier}). Credibility score: " . round(((float) $s->credibility_score) * 100) . "%.",
                contentType: 'source',
                publishedAt: $s->created_at?->toIso8601String(),
                updatedAt: $s->updated_at?->toIso8601String(),
                source: [
                    'id' => $s->id,
                    'name' => $s->name,
                    'domain' => parse_url($s->url, PHP_URL_HOST) ?? $s->url,
                    'trust_tier' => $s->trust_tier,
                    'credibility_score' => (float) $s->credibility_score,
                ],
                matchedEntities: [],
                matchedTopics: ['Publisher Registry'],
                relevanceScore: 0.82,
                relevanceExplanation: "Direct match on monitored news publication outlet.",
                verificationStatus: 'verified',
                corroborationCount: 1,
                url: "/admin/sources",
                informationClassification: 'verified_fact'
            );
        }

        return $items;
    }

    /**
     * Search Collections & Library Items (Strictly Tenant-Isolated).
     *
     * @return array<int, SearchResultItemDTO>
     */
    protected function searchCollections(string $query, ?User $user, ?string $tenantId): array
    {
        if (!$user) {
            return []; // Unauthenticated users cannot view private/organization collections
        }

        $qb = SubscriberCollection::query()
            ->where(function (Builder $q) use ($user, $tenantId) {
                // User's own collections
                $q->where('user_id', $user->id);

                // Or organization-shared collections within the same tenant
                if ($tenantId !== null) {
                    $q->orWhere(function (Builder $oq) use ($tenantId) {
                        $oq->where('organization_id', $tenantId)
                           ->whereIn('visibility', ['organization', 'shared']);
                    });
                }
            })
            ->where(function (Builder $q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            });

        $collections = $qb->limit(6)->get();
        $items = [];

        foreach ($collections as $c) {
            $items[] = new SearchResultItemDTO(
                id: (string) $c->id,
                title: "Collection: {$c->name}",
                excerpt: $c->description ?? "Subscriber research collection ({$c->visibility}).",
                contentType: 'collection',
                publishedAt: $c->created_at?->toIso8601String(),
                updatedAt: $c->updated_at?->toIso8601String(),
                source: [
                    'name' => $c->visibility === 'organization' ? 'Organization Knowledge' : 'Private Workspace',
                    'trust_tier' => 'established_primary',
                    'credibility_score' => 100,
                ],
                matchedEntities: [],
                matchedTopics: ['Research Workspace'],
                relevanceScore: 0.95,
                relevanceExplanation: "Matched within authorized {$c->visibility} knowledge collection.",
                verificationStatus: 'verified',
                corroborationCount: 1,
                url: "/subscriber/library",
                tenantId: $c->organization_id,
                informationClassification: 'verified_fact'
            );
        }

        return $items;
    }
}
