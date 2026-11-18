<?php

declare(strict_types=1);

namespace App\Services\Workspace;

use App\Models\Article;
use App\Models\Company;
use App\Models\Executive;
use App\Models\FollowedCompany;
use App\Models\FollowedExecutive;
use App\Models\IntelligenceEntity;
use App\Models\IntelligenceEvent;
use App\Models\IntelligenceTrend;
use App\Models\Publication;
use App\Models\ResearchReport;
use App\Models\StoryCluster;
use App\Models\SubscriberInteraction;
use App\Models\SubscriberWatchlistItem;
use App\Models\Technology;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PersonalizedFeedService
{
    /**
     * Generate an intelligent executive baseline profile for a new user with zero prior activity (Cold Start).
     */
    public function generateBaselineColdStartProfile(User $user): UserPreference
    {
        return UserPreference::updateOrCreate(
            ['user_id' => $user->id],
            [
                'preferred_industries' => ['Artificial Intelligence', 'Semiconductors', 'Cloud Infrastructure', 'Cybersecurity'],
                'preferred_topics' => ['Generative AI', 'Chip Manufacturing', 'Autonomous Agents', 'Enterprise Cloud', 'Cyber Defense'],
                'preferred_companies' => ['Nvidia', 'OpenAI', 'Microsoft', 'Google', 'TSMC'],
                'personalization_enabled' => true,
                'receive_breaking_alerts' => true,
                'receive_pdf_briefings' => true,
                'digest_frequency' => 'daily',
                'email_frequency' => 'daily',
            ]
        );
    }

    /**
     * Build dynamic RAG subscriber profile context with interaction-weighted affinities.
     * Weights: share = 4.0, save = 3.0, search = 2.0, view = 1.0.
     *
     * @return array{
     *   is_cold_start: bool,
     *   interaction_count: int,
     *   top_topics: array<string>,
     *   top_categories: array<string>,
     *   top_companies: array<string>,
     *   search_keywords: array<string>,
     *   topic_weights: array<string, float>,
     *   category_weights: array<string, float>,
     *   company_weights: array<string, float>
     * }
     */
    public function buildRAGSubscriberProfileContext(User $user): array
    {
        $preferences = $user->preferences;
        if (!$preferences || empty($preferences->preferred_topics) || empty($preferences->preferred_industries)) {
            $preferences = $this->generateBaselineColdStartProfile($user);
        }

        $interactions = $user->interactions()
            ->latest('created_at')
            ->limit(100)
            ->get();

        $isColdStart = $interactions->isEmpty();

        $weights = [
            'share' => 4.0,
            'save' => 3.0,
            'search' => 2.0,
            'view' => 1.0,
        ];

        $topicWeights = [];
        $categoryWeights = [];
        $companyWeights = [];
        $searchKeywords = [];

        // Baseline seed weights from user preferences
        foreach ((array) ($preferences->preferred_topics ?? []) as $t) {
            $tKey = strtolower(trim((string) $t));
            if ($tKey !== '') {
                $topicWeights[$tKey] = ($topicWeights[$tKey] ?? 0.0) + 2.0;
            }
        }
        foreach ((array) ($preferences->preferred_industries ?? []) as $ind) {
            $indKey = strtolower(trim((string) $ind));
            if ($indKey !== '') {
                $categoryWeights[$indKey] = ($categoryWeights[$indKey] ?? 0.0) + 2.0;
            }
        }
        foreach ((array) ($preferences->preferred_companies ?? []) as $cmp) {
            $cmpKey = strtolower(trim((string) $cmp));
            if ($cmpKey !== '') {
                $companyWeights[$cmpKey] = ($companyWeights[$cmpKey] ?? 0.0) + 2.0;
            }
        }

        // Fold in followed portfolio
        foreach ($this->collectFollowedEntityNames($user) as $fn) {
            $fnKey = strtolower(trim((string) $fn));
            if ($fnKey !== '') {
                $companyWeights[$fnKey] = ($companyWeights[$fnKey] ?? 0.0) + 3.0;
            }
        }

        // Process subscriber interaction signals using RAG methodology
        $articleIds = $interactions->where('target_type', 'article')
            ->pluck('target_id')
            ->filter()
            ->unique()
            ->toArray();

        $articlesMap = !empty($articleIds)
            ? Article::whereIn('id', $articleIds)
                ->with(['categories:id,name,slug', 'topics:id,name', 'companies:id,name'])
                ->get()
                ->keyBy('id')
            : collect();

        foreach ($interactions as $interaction) {
            $w = $weights[$interaction->interaction_type] ?? 1.0;

            if ($interaction->interaction_type === 'search') {
                $queryStr = trim((string) ($interaction->metadata['query'] ?? $interaction->target_id ?? ''));
                if (!empty($queryStr)) {
                    $terms = array_filter(explode(' ', strtolower($queryStr)), fn($t) => strlen($t) > 2);
                    foreach ($terms as $term) {
                        $searchKeywords[$term] = ($searchKeywords[$term] ?? 0.0) + $w;
                    }
                }
            } elseif ($interaction->target_type === 'article' && isset($articlesMap[$interaction->target_id])) {
                $art = $articlesMap[$interaction->target_id];

                foreach ($art->categories as $c) {
                    $name = strtolower(trim($c->name));
                    if ($name !== '') {
                        $categoryWeights[$name] = ($categoryWeights[$name] ?? 0.0) + $w;
                    }
                }

                foreach ($art->topics as $t) {
                    $tName = strtolower(trim($t->name));
                    if ($tName !== '') {
                        $topicWeights[$tName] = ($topicWeights[$tName] ?? 0.0) + $w;
                    }
                }

                foreach ($art->companies as $cmp) {
                    $cmpName = strtolower(trim($cmp->name));
                    if ($cmpName !== '') {
                        $companyWeights[$cmpName] = ($companyWeights[$cmpName] ?? 0.0) + $w;
                    }
                }
            }
        }

        arsort($topicWeights);
        arsort($categoryWeights);
        arsort($companyWeights);
        arsort($searchKeywords);

        return [
            'is_cold_start' => $isColdStart,
            'interaction_count' => $interactions->count(),
            'top_topics' => array_slice(array_keys($topicWeights), 0, 10),
            'top_categories' => array_slice(array_keys($categoryWeights), 0, 10),
            'top_companies' => array_slice(array_keys($companyWeights), 0, 10),
            'search_keywords' => array_slice(array_keys($searchKeywords), 0, 10),
            'topic_weights' => $topicWeights,
            'category_weights' => $categoryWeights,
            'company_weights' => $companyWeights,
        ];
    }

    /**
     * Build personalized feed grouped into structured intelligence sections.
     */
    public function getPersonalizedFeed(User $user, ?string $section = null): array
    {
        $ragProfile = $this->buildRAGSubscriberProfileContext($user);
        $preferences = $user->preferences;
        $watchlists = $user->watchlists()->with('items')->get();

        // Collect followed entity names and topics
        $followedEntityNames = $this->collectFollowedEntityNames($user);

        $preferredTopics = !empty($ragProfile['top_topics'])
            ? array_map('ucwords', $ragProfile['top_topics'])
            : (array) ($preferences?->preferred_topics ?? ['Artificial Intelligence', 'Semiconductors', 'Cloud Infrastructure']);

        $preferredIndustries = !empty($ragProfile['top_categories'])
            ? array_map('ucwords', $ragProfile['top_categories'])
            : (array) ($preferences?->preferred_industries ?? ['Technology', 'Semiconductors']);

        // Check if personalization is active
        $personalizationActive = $preferences ? $preferences->personalization_enabled : true;

        // Hidden items
        $hiddenIds = $user->interactions()
            ->where('interaction_type', 'hide')
            ->pluck('target_id')
            ->filter()
            ->toArray();

        // Build Sections
        $feed = [
            'for_you' => $this->buildForYouSection($user, $followedEntityNames, $preferredTopics, $hiddenIds, $personalizationActive, $ragProfile),
            'following' => $this->buildFollowingSection($user, $followedEntityNames, $hiddenIds),
            'breaking' => $this->buildBreakingSection($hiddenIds),
            'trending' => $this->buildTrendingSection($preferredTopics),
            'companies' => $this->buildCompanyDevelopmentsSection($followedEntityNames, $hiddenIds),
            'technologies' => $this->buildTechnologySection($hiddenIds),
            'industries' => $this->buildIndustrySection($preferredIndustries, $hiddenIds),
            'saved' => $this->buildSavedSection($user),
            'recommended_research' => $this->buildResearchSection($user, $preferredTopics),
        ];

        if ($section && isset($feed[$section])) {
            return [$section => $feed[$section]];
        }

        return $feed;
    }

    /**
     * Section 1: For You (Strict user-profile grounded algorithmic intelligence)
     * Fully personalized against subscriber's followed portfolio, preferred topics, and reading history.
     */
    protected function buildForYouSection(User $user, array $entityNames, array $topics, array $hiddenIds, bool $active, ?array $ragProfile = null): array
    {
        $preferences = $user->preferences;
        $preferredIndustries = (array) ($preferences?->preferred_industries ?? []);

        // Behavioral reading history
        $interactedArticleIds = $user->interactions()
            ->whereIn('interaction_type', ['view', 'save', 'share'])
            ->latest('created_at')
            ->limit(30)
            ->pluck('target_id')
            ->filter()
            ->toArray();

        // 1. Build strictly profile-grounded query
        $query = Article::whereNotIn('id', $hiddenIds)
            ->whereIn('verification_status', ['verified', 'confirmed', 'reported_reliable', 'developing'])
            ->with(['source:id,name,credibility_score', 'companies:id,name,ticker', 'categories:id,name,slug,color', 'subcategories:id,name,slug']);

        $hasProfileSignals = !empty($entityNames) || !empty($topics) || !empty($preferredIndustries) || !empty($interactedArticleIds);

        if ($hasProfileSignals) {
            $query->where(function ($q) use ($entityNames, $topics, $preferredIndustries, $interactedArticleIds) {
                // Match followed entities by title, content, or company relation
                if (!empty($entityNames)) {
                    foreach ($entityNames as $name) {
                        $q->orWhere('title', 'like', "%{$name}%")
                          ->orWhere('content_normalized', 'like', "%{$name}%");
                    }
                    $q->orWhereHas('companies', function ($cq) use ($entityNames) {
                        $cq->whereIn('name', $entityNames);
                    });
                }

                // Match preferred topics
                if (!empty($topics)) {
                    foreach ($topics as $top) {
                        $q->orWhere('title', 'like', "%{$top}%")
                          ->orWhere('content_normalized', 'like', "%{$top}%");
                    }
                    $q->orWhereHas('topics', function ($tq) use ($topics) {
                        $tq->whereIn('name', $topics);
                    });
                }

                // Match preferred industries / categories
                if (!empty($preferredIndustries)) {
                    $q->orWhereHas('categories', function ($catQ) use ($preferredIndustries) {
                        $catQ->whereIn('name', $preferredIndustries)
                             ->orWhereIn('slug', array_map('Str::slug', $preferredIndustries));
                    });
                }

                // Match previously interacted / similar articles
                if (!empty($interactedArticleIds)) {
                    $q->orWhereIn('id', $interactedArticleIds);
                }
            });
        }

        $articles = $query->latest('published_at')->limit(20)->get();

        // If user profile is pristine/brand new and matches were sparse, backfill with top tier verified reports
        if ($articles->count() < 5) {
            $fallbackArticles = Article::whereNotIn('id', array_merge($hiddenIds, $articles->pluck('id')->toArray()))
                ->whereIn('verification_status', ['verified', 'confirmed', 'reported_reliable'])
                ->with(['source:id,name,credibility_score', 'companies:id,name,ticker', 'categories:id,name,slug,color', 'subcategories:id,name,slug'])
                ->orderByDesc('importance_score')
                ->latest('published_at')
                ->limit(10 - $articles->count())
                ->get();
            $articles = $articles->concat($fallbackArticles);
        }

        $items = [];
        $savedIds = $user->libraryItems()->where('item_type', 'article')->pluck('item_id')->toArray();

        foreach ($articles as $article) {
            $explanation = 'Grounded in your institutional intelligence profile';
            $score = 70;
            $matchedReason = 'profile';

            // Boost score and assign transparent explanation if entity matches
            if (!empty($entityNames)) {
                foreach ($entityNames as $ent) {
                    if (str_contains(strtolower($article->title), strtolower($ent)) ||
                        str_contains(strtolower($article->content_normalized ?? ''), strtolower($ent)) ||
                        $article->companies->contains('name', $ent)) {
                        $explanation = "Because you follow {$ent}";
                        $score += 30;
                        $matchedReason = 'followed_entity';
                        break;
                    }
                }
            }

            // Check topic match
            if ($matchedReason === 'profile' && !empty($topics)) {
                foreach ($topics as $top) {
                    if (str_contains(strtolower($article->title), strtolower($top)) ||
                        str_contains(strtolower($article->content_normalized ?? ''), strtolower($top))) {
                        $explanation = "Matched to your focus in {$top}";
                        $score += 20;
                        $matchedReason = 'preferred_topic';
                        break;
                    }
                }
            }

            // Check industry match
            if ($matchedReason === 'profile' && !empty($preferredIndustries)) {
                foreach ($article->categories as $cat) {
                    if (in_array($cat->name, $preferredIndustries, true)) {
                        $explanation = "Monitored industry development in {$cat->name}";
                        $score += 15;
                        $matchedReason = 'preferred_industry';
                        break;
                    }
                }
            }

            // Evaluate RAG interaction weights if present
            if ($ragProfile) {
                if (!empty($ragProfile['topic_weights']) && $article->relationLoaded('topics')) {
                    foreach ($article->topics as $t) {
                        $tLow = strtolower($t->name);
                        if (isset($ragProfile['topic_weights'][$tLow])) {
                            $score += (int) min(20, round($ragProfile['topic_weights'][$tLow] * 2.5));
                            $explanation = "RAG Profile Match: High affinity with your interests in {$t->name}";
                            $matchedReason = 'rag_topic';
                            break;
                        }
                    }
                }
            }

            // Boost for importance score
            $score += (int) (($article->importance_score ?? 5.0) * 2);

            $rawText = strip_tags($article->content_normalized ?? $article->content_raw ?? '');
            $category = $article->categories->first();
            $remoteImage = $article->featured_image ?? $article->hero_image ?? null;
            $sourceName = $article->source?->name ?? 'Wire Service';
            $attribution = $article->featured_image_caption ?: ($article->source ? "Source: {$sourceName}" : 'Source: Wire Service');

            $items[] = [
                'id' => $article->id,
                'type' => 'article',
                'title' => $article->title,
                'headline' => $article->title,
                'slug' => $article->slug,
                'summary' => substr($rawText, 0, 220) . (strlen($rawText) > 220 ? '...' : ''),
                'primary_image' => $remoteImage,
                'featured_image' => $remoteImage,
                'featured_image_caption' => $attribution,
                'source_attribution' => $attribution,
                'source' => [
                    'name' => $sourceName,
                    'credibility_score' => $article->source?->credibility_score ?? 9.2,
                ],
                'category' => $category ? [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'color' => $category->color ?? '#147A67',
                ] : [
                    'name' => 'Strategic Technology',
                    'slug' => 'strategic-technology',
                    'color' => '#147A67',
                ],
                'subcategories' => $article->subcategories->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug])->toArray(),
                'published_at' => $article->published_at?->toIso8601String(),
                'verification_status' => $article->verification_status ?? 'confirmed',
                'importance_score' => $article->importance_score,
                'personalization_explanation' => $explanation,
                'match_reason' => $matchedReason,
                'relevance_score' => min(99, $score),
                'is_saved' => in_array($article->id, $savedIds, true),
                'is_profile_grounded' => true,
            ];
        }

        // Sort descending by relevance score
        usort($items, fn ($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return array_slice($items, 0, 10);
    }

    /**
     * Section 2: Following (Direct updates from followed entities)
     */
    protected function buildFollowingSection(User $user, array $entityNames, array $hiddenIds): array
    {
        if (empty($entityNames)) {
            return [];
        }

        $query = Article::whereNotIn('id', $hiddenIds)
            ->where(function ($q) use ($entityNames) {
                foreach ($entityNames as $name) {
                    $q->orWhere('title', 'like', "%{$name}%")
                      ->orWhere('content_normalized', 'like', "%{$name}%");
                }
            })
            ->with(['source', 'companies'])
            ->latest('published_at')
            ->limit(8)
            ->get();

        $results = [];
        foreach ($query as $art) {
            $matchedEntity = null;
            foreach ($entityNames as $name) {
                if (str_contains(strtolower($art->title), strtolower($name))) {
                    $matchedEntity = $name;
                    break;
                }
            }
            $results[] = [
                'id' => $art->id,
                'type' => 'article',
                'title' => $art->title,
                'slug' => $art->slug,
                'summary' => substr(strip_tags($art->content_normalized ?? $art->content_raw), 0, 180) . '...',
                'source' => $art->source?->name,
                'published_at' => $art->published_at?->toIso8601String(),
                'verification_status' => $art->verification_status,
                'personalization_explanation' => "Direct update on {$matchedEntity}",
            ];
        }

        return $results;
    }

    /**
     * Section 3: Breaking Intelligence
     */
    protected function buildBreakingSection(array $hiddenIds): array
    {
        return Article::whereNotIn('id', $hiddenIds)
            ->where('is_breaking', true)
            ->orWhere('importance_score', '>=', 8.5)
            ->with('source')
            ->latest('published_at')
            ->limit(5)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'type' => 'article',
                'title' => $a->title,
                'slug' => $a->slug,
                'is_breaking' => true,
                'importance_score' => $a->importance_score,
                'verification_status' => $a->verification_status,
                'published_at' => $a->published_at?->toIso8601String(),
                'personalization_explanation' => 'Breaking sovereign or executive market action',
            ])
            ->toArray();
    }

    /**
     * Section 4: Trending in Your Interests
     */
    protected function buildTrendingSection(array $topics): array
    {
        return IntelligenceTrend::latest('velocity_score')
            ->limit(5)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => 'trend',
                'title' => $t->title,
                'category' => $t->category,
                'velocity_score' => $t->velocity_score,
                'direction' => $t->trend_direction,
                'summary' => $t->summary,
                'personalization_explanation' => 'Accelerating signal across monitored research topics',
            ])
            ->toArray();
    }

    /**
     * Section 5: Company Developments
     */
    protected function buildCompanyDevelopmentsSection(array $entityNames, array $hiddenIds): array
    {
        return IntelligenceEvent::latest('occurred_at')
            ->limit(6)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'type' => 'event',
                'title' => $e->title,
                'event_category' => $e->event_category,
                'summary' => $e->summary,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
                'verification_status' => $e->verification_status,
                'personalization_explanation' => 'Verified corporate disclosure milestone',
            ])
            ->toArray();
    }

    /**
     * Section 6: Technology Watch
     */
    protected function buildTechnologySection(array $hiddenIds): array
    {
        return StoryCluster::whereIn('status', ['synthesized', 'published'])
            ->latest('updated_at')
            ->limit(4)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'type' => 'cluster',
                'title' => $c->title ?? $c->headline,
                'slug' => $c->slug,
                'summary' => $c->summary,
                'confidence_score' => $c->confidence_score,
                'personalization_explanation' => 'Multi-source synthesized technology cluster',
            ])
            ->toArray();
    }

    /**
     * Section 7: Industry Intelligence
     */
    protected function buildIndustrySection(array $industries, array $hiddenIds): array
    {
        return Article::whereNotIn('id', $hiddenIds)
            ->latest('published_at')
            ->limit(6)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'type' => 'article',
                'title' => $a->title,
                'slug' => $a->slug,
                'published_at' => $a->published_at?->toIso8601String(),
                'verification_status' => $a->verification_status,
                'personalization_explanation' => 'Monitored technology sector update',
            ])
            ->toArray();
    }

    /**
     * Section 8: Saved for Later
     */
    protected function buildSavedSection(User $user): array
    {
        return $user->libraryItems()
            ->with('collection')
            ->latest('created_at')
            ->limit(4)
            ->get()
            ->map(function ($it) {
                $res = $it->resolveResource();
                return [
                    'id' => $it->id,
                    'item_type' => $it->item_type,
                    'item_id' => $it->item_id,
                    'title' => $res?->title ?? $res?->headline ?? 'Saved Record',
                    'private_notes' => $it->private_notes,
                    'collection_name' => $it->collection?->name,
                    'saved_at' => $it->created_at?->toIso8601String(),
                ];
            })
            ->toArray();
    }

    /**
     * Section 9: Recommended Research & Publications
     */
    protected function buildResearchSection(User $user, array $topics): array
    {
        $publications = Publication::where('status', 'published')
            ->latest('published_at')
            ->limit(3)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'type' => 'publication',
                'title' => $p->title,
                'subtitle' => $p->subtitle,
                'edition_number' => $p->edition_number,
                'page_count' => $p->page_count,
                'personalization_explanation' => 'Latest executive dossier edition',
            ]);

        $reports = ResearchReport::where('editorial_status', 'approved')
            ->latest('created_at')
            ->limit(2)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'type' => 'research_report',
                'title' => $r->title,
                'summary' => $r->executive_summary,
                'personalization_explanation' => 'Grounded deep research analysis',
            ]);

        return $publications->concat($reports)->toArray();
    }

    /**
     * Record interaction signal.
     */
    public function recordInteraction(User $user, string $action, string $targetType, ?string $targetId, ?array $metadata = null): SubscriberInteraction
    {
        return $user->interactions()->create([
            'interaction_type' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /**
     * Clear reading history for the user.
     */
    public function clearReadingHistory(User $user): int
    {
        return $user->interactions()
            ->whereIn('interaction_type', ['view', 'search'])
            ->delete();
    }

    /**
     * Reset personalization signals.
     */
    public function resetPersonalizationSignals(User $user): int
    {
        return $user->interactions()->delete();
    }

    /**
     * Collect all entity names followed by subscriber across watchlists, preferences, and direct follows.
     *
     * @return array<int, string>
     */
    public function collectFollowedEntityNames(User $user): array
    {
        $names = [];

        // Watchlist items
        $watchlists = $user->watchlists()->with('items')->get();
        foreach ($watchlists as $wl) {
            foreach ($wl->items as $it) {
                if (!empty($it->entity_name)) {
                    $names[] = $it->entity_name;
                }
            }
        }

        // Direct followed companies
        $followedCompanyIds = FollowedCompany::where('user_id', $user->id)->pluck('company_id');
        if ($followedCompanyIds->isNotEmpty()) {
            foreach (Company::whereIn('id', $followedCompanyIds)->pluck('name') as $name) {
                $names[] = $name;
            }
        }

        // Direct followed executives
        $followedExecIds = FollowedExecutive::where('user_id', $user->id)->pluck('executive_id');
        if ($followedExecIds->isNotEmpty()) {
            foreach (Executive::whereIn('id', $followedExecIds)->pluck('full_name') as $name) {
                $names[] = $name;
            }
        }

        // User preference topics and companies
        $preferences = $user->preferences;
        if ($preferences) {
            if (!empty($preferences->preferred_companies)) {
                $names = array_merge($names, (array) $preferences->preferred_companies);
            }
            if (!empty($preferences->preferred_executives)) {
                $names = array_merge($names, (array) $preferences->preferred_executives);
            }
            if (!empty($preferences->preferred_topics)) {
                $names = array_merge($names, (array) $preferences->preferred_topics);
            }
        }

        return array_values(array_unique(array_filter($names)));
    }

    /**
     * Compute comprehensive overview statistics and executive briefing for subscriber dashboard.
     */
    public function getSubscriberOverview(User $user): array
    {
        $watchlists = $user->watchlists()->withCount('items')->get();
        $followedNames = $this->collectFollowedEntityNames($user);

        $followedCompaniesCount = FollowedCompany::where('user_id', $user->id)->count();
        $followedExecutivesCount = FollowedExecutive::where('user_id', $user->id)->count();
        $watchlistItemsCount = $watchlists->sum('items_count');
        $totalFollowed = max(count($followedNames), $followedCompaniesCount + $followedExecutivesCount + $watchlistItemsCount);

        $activeMonitors = $user->monitors()->where('is_active', true)->count();
        $savedIntelligence = $user->libraryItems()->count();
        $unreadNotifications = $user->notifications()->whereNull('read_at')->count();
        $recentResearch = $user->interactions()->where('interaction_type', 'search')->count();

        // Time-aware greeting
        $hour = (int) now()->format('H');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

        // Followed company updates
        $followedUpdates = [];
        if (!empty($followedNames)) {
            $followedUpdates = Article::where(function ($q) use ($followedNames) {
                foreach ($followedNames as $name) {
                    $q->orWhere('title', 'like', "%{$name}%")
                      ->orWhere('content_normalized', 'like', "%{$name}%");
                }
            })
            ->with(['source:id,name', 'categories:id,name,slug,color', 'companies:id,name,ticker'])
            ->latest('published_at')
            ->limit(5)
            ->get()
            ->map(fn ($a) => $this->formatFeedItem($a, $user, 'Direct development in followed portfolio'))
            ->toArray();
        }

        if (empty($followedUpdates)) {
            $followedUpdates = Article::with(['source:id,name', 'categories:id,name,slug,color', 'companies:id,name,ticker'])
                ->latest('published_at')
                ->limit(4)
                ->get()
                ->map(fn ($a) => $this->formatFeedItem($a, $user, 'Key technology market signal'))
                ->toArray();
        }

        // Trending technology stories
        $trendingStories = Article::where(function ($q) {
                $q->where('is_breaking', true)
                  ->orWhere('importance_score', '>=', 8.0);
            })
            ->with(['source:id,name', 'categories:id,name,slug,color'])
            ->latest('published_at')
            ->limit(4)
            ->get()
            ->map(fn ($a) => $this->formatFeedItem($a, $user, 'High-velocity intelligence trend'))
            ->toArray();

        // Recent executive publications
        $recentReports = Publication::where('status', 'published')
            ->latest('publication_date')
            ->limit(3)
            ->get(['id', 'title', 'subtitle', 'edition_number', 'publication_date', 'page_count'])
            ->toArray();

        // Saved intelligence shortcuts
        $savedShortcuts = $user->libraryItems()
            ->latest('created_at')
            ->limit(3)
            ->get()
            ->map(function ($it) {
                $res = $it->resolveResource();
                return [
                    'id' => $it->id,
                    'title' => $res?->title ?? $res?->headline ?? 'Saved Record',
                    'item_type' => $it->item_type,
                    'item_id' => $it->item_id,
                    'saved_at' => $it->created_at?->diffForHumans() ?? 'Recently',
                ];
            })
            ->toArray();

        return [
            'greeting' => $greeting,
            'date' => now()->format('l, F j, Y'),
            'summary' => 'Your intelligence environment is active. Real-time signals are tracking monitored semiconductor, artificial intelligence, and enterprise technology portfolios.',
            'metrics' => [
                'followed_entities' => $totalFollowed,
                'active_watchlists' => $watchlists->count(),
                'active_monitors' => $activeMonitors,
                'saved_intelligence' => $savedIntelligence,
                'unread_notifications' => $unreadNotifications,
                'recent_research' => $recentResearch,
            ],
            'followed_updates' => $followedUpdates,
            'trending_stories' => $trendingStories,
            'recent_reports' => $recentReports,
            'saved_shortcuts' => $savedShortcuts,
        ];
    }

    /**
     * Get paginated stream of intelligence items with dynamic category and multi-tier sorting.
     * Enforces an 80% user interest / 20% diverse serendipity ratio on the overview feed.
     */
    public function getFeedStream(User $user, array $filters = []): array
    {
        $category = $filters['category'] ?? null;
        $sort = $filters['sort'] ?? 'relevance';
        $search = $filters['q'] ?? ($filters['search'] ?? null);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(50, max(5, (int) ($filters['per_page'] ?? 15)));

        // Hidden items
        $hiddenIds = $user->interactions()
            ->where('interaction_type', 'hide')
            ->pluck('target_id')
            ->filter()
            ->toArray();

        $followedNames = $this->collectFollowedEntityNames($user);
        $savedArticleIds = $user->libraryItems()->where('item_type', 'article')->pluck('item_id')->toArray();

        $isOverviewFeed = (empty($category) || $category === 'all' || $category === 'All Intelligence') && empty($search) && (empty($sort) || $sort === 'relevance');

        // When viewing the main Overview Feed, apply 80% user interest / 20% diverse discovery ratio with RAG methodology
        if ($isOverviewFeed) {
            $ragProfile = $this->buildRAGSubscriberProfileContext($user);
            $preferences = $user->preferences;
            $preferredTopics = !empty($ragProfile['top_topics'])
                ? $ragProfile['top_topics']
                : (array) ($preferences?->preferred_topics ?? ['artificial intelligence', 'semiconductors', 'cloud infrastructure']);
            $preferredIndustries = !empty($ragProfile['top_categories'])
                ? $ragProfile['top_categories']
                : (array) ($preferences?->preferred_industries ?? ['technology', 'semiconductors']);
            $searchKeywords = $ragProfile['search_keywords'] ?? [];

            $targetInterestCount = (int) max(1, (int) round($perPage * 0.8));
            $targetDiverseCount = (int) max(1, $perPage - $targetInterestCount);

            // 1. User Interest Query (80% - Profile Grounded & RAG Retrieved)
            $interestQuery = Article::whereNotIn('id', $hiddenIds)
                ->whereIn('verification_status', ['verified', 'confirmed', 'reported_reliable', 'developing'])
                ->with(['source:id,name,credibility_score,url', 'categories:id,name,slug,color', 'subcategories:id,name,slug', 'companies:id,name,slug,ticker', 'topics:id,name']);

            $interestQuery->where(function ($q) use ($followedNames, $preferredTopics, $preferredIndustries, $searchKeywords) {
                if (!empty($followedNames)) {
                    foreach ($followedNames as $name) {
                        $q->orWhere('title', 'like', "%{$name}%")
                          ->orWhere('content_normalized', 'like', "%{$name}%");
                    }
                    $q->orWhereHas('companies', function ($cq) use ($followedNames) {
                        $cq->whereIn('name', $followedNames);
                    });
                }
                if (!empty($preferredTopics)) {
                    foreach ($preferredTopics as $top) {
                        $q->orWhere('title', 'like', "%{$top}%")
                          ->orWhere('content_normalized', 'like', "%{$top}%");
                    }
                    $q->orWhereHas('topics', function ($tq) use ($preferredTopics) {
                        $tq->whereIn('name', $preferredTopics);
                    });
                }
                if (!empty($preferredIndustries)) {
                    $q->orWhereHas('categories', function ($catQ) use ($preferredIndustries) {
                        $catQ->whereIn('name', $preferredIndustries)
                             ->orWhereIn('slug', array_map('Illuminate\Support\Str::slug', $preferredIndustries));
                    });
                }
                if (!empty($searchKeywords)) {
                    foreach ($searchKeywords as $kw) {
                        $q->orWhere('title', 'like', "%{$kw}%");
                    }
                }
            });

            $totalInterest = (clone $interestQuery)->count();
            $candidateInterestArticles = $interestQuery->orderByDesc('importance_score')
                ->latest('published_at')
                ->limit(60)
                ->get();

            // RAG Dynamic Affinity Re-ranking based on interaction weights
            $scoredInterestArticles = $candidateInterestArticles->map(function ($article) use ($ragProfile) {
                $score = 50.0;
                $explanation = 'Grounded in your institutional intelligence profile';

                // Topic affinity (RAG weighted)
                if ($article->relationLoaded('topics') && $article->topics) {
                    foreach ($article->topics as $t) {
                        $tLow = strtolower($t->name);
                        if (isset($ragProfile['topic_weights'][$tLow])) {
                            $score += min(25.0, $ragProfile['topic_weights'][$tLow] * 3.0);
                            $explanation = "RAG Profile Match: Grounded in your focus on {$t->name}";
                        }
                    }
                }

                // Category affinity
                if ($article->relationLoaded('categories') && $article->categories) {
                    foreach ($article->categories as $c) {
                        $cLow = strtolower($c->name);
                        if (isset($ragProfile['category_weights'][$cLow])) {
                            $score += min(20.0, $ragProfile['category_weights'][$cLow] * 2.5);
                        }
                    }
                }

                // Followed or monitored company affinity
                if ($article->relationLoaded('companies') && $article->companies) {
                    foreach ($article->companies as $cmp) {
                        $cmpLow = strtolower($cmp->name);
                        if (isset($ragProfile['company_weights'][$cmpLow])) {
                            $score += min(25.0, $ragProfile['company_weights'][$cmpLow] * 3.5);
                            $explanation = "Because you follow {$cmp->name}";
                        }
                    }
                }

                // Importance score contribution
                $score += ($article->importance_score ?? 5.0) * 1.5;

                $article->rag_affinity_score = min(99, (int) round($score));
                $article->rag_explanation = $explanation;
                return $article;
            })->sortByDesc('rag_affinity_score')->values();

            // Slice current page of interest items
            $interestArticles = $scoredInterestArticles->slice(($page - 1) * $targetInterestCount, $targetInterestCount)->values();

            // If user is brand new or candidate matches are sparse, backfill from verified reports
            if ($interestArticles->count() < $targetInterestCount) {
                $needed = $targetInterestCount - $interestArticles->count();
                $existingIds = array_merge($hiddenIds, $interestArticles->pluck('id')->toArray());
                $backfill = Article::whereNotIn('id', $existingIds)
                    ->whereIn('verification_status', ['verified', 'confirmed', 'reported_reliable'])
                    ->with(['source:id,name,credibility_score,url', 'categories:id,name,slug,color', 'subcategories:id,name,slug', 'companies:id,name,slug,ticker', 'topics:id,name'])
                    ->orderByDesc('importance_score')
                    ->latest('published_at')
                    ->limit($needed)
                    ->get();
                $interestArticles = $interestArticles->concat($backfill);
            }

            $usedIds = array_merge($hiddenIds, $interestArticles->pluck('id')->toArray());

            // 2. Diverse Discovery Query (20% - outside primary user interests to prevent filter bubble)
            $diverseQuery = Article::whereNotIn('id', $usedIds)
                ->whereIn('verification_status', ['verified', 'confirmed', 'reported_reliable', 'developing'])
                ->with(['source:id,name,credibility_score,url', 'categories:id,name,slug,color', 'subcategories:id,name,slug', 'companies:id,name,slug,ticker', 'topics:id,name']);

            // Exclude user's top categories if possible to ensure real diversity
            if (!empty($preferredIndustries)) {
                $diverseQuery->whereDoesntHave('categories', function ($cq) use ($preferredIndustries) {
                    $cq->whereIn('name', $preferredIndustries)
                       ->orWhereIn('slug', array_map('Illuminate\Support\Str::slug', $preferredIndustries));
                });
            }

            $totalDiverse = (clone $diverseQuery)->count();
            $diverseArticles = $diverseQuery->orderByDesc('importance_score')
                ->latest('published_at')
                ->forPage($page, $targetDiverseCount)
                ->get();

            // Fallback backfill if diverse pool had fewer items
            if ($diverseArticles->count() < $targetDiverseCount) {
                $neededDiverse = $targetDiverseCount - $diverseArticles->count();
                $existingDiverseIds = array_merge($usedIds, $diverseArticles->pluck('id')->toArray());
                $diverseBackfill = Article::whereNotIn('id', $existingDiverseIds)
                    ->with(['source:id,name,credibility_score,url', 'categories:id,name,slug,color', 'subcategories:id,name,slug', 'companies:id,name,slug,ticker', 'topics:id,name'])
                    ->latest('published_at')
                    ->limit($neededDiverse)
                    ->get();
                $diverseArticles = $diverseArticles->concat($diverseBackfill);
            }

            // Format items with explicit 80/20 attribution
            $formattedInterest = [];
            foreach ($interestArticles as $art) {
                $item = $this->formatFeedItem($art, $user, $art->rag_explanation ?? null, $followedNames, $savedArticleIds);
                $item['match_type'] = 'interest';
                $item['is_interest_match'] = true;
                $item['is_serendipity'] = false;
                $item['personalization_factor'] = '80% User Interests (RAG Adaptive)';
                if (isset($art->rag_affinity_score)) {
                    $item['relevance_score'] = $art->rag_affinity_score;
                }
                $formattedInterest[] = $item;
            }

            $formattedDiverse = [];
            foreach ($diverseArticles as $art) {
                $item = $this->formatFeedItem(
                    $art,
                    $user,
                    'Diverse Discovery: Key development outside your primary focus to broaden strategic intelligence',
                    $followedNames,
                    $savedArticleIds
                );
                $item['match_type'] = 'diverse';
                $item['is_interest_match'] = false;
                $item['is_serendipity'] = true;
                $item['personalization_factor'] = '20% Diverse Discovery';
                $formattedDiverse[] = $item;
            }

            // Interleave items cleanly: 4 interest, 1 diverse, 4 interest, 1 diverse...
            $blendedItems = [];
            $iIdx = 0;
            $dIdx = 0;
            $iCount = count($formattedInterest);
            $dCount = count($formattedDiverse);

            while ($iIdx < $iCount || $dIdx < $dCount) {
                for ($k = 0; $k < 4 && $iIdx < $iCount; $k++) {
                    $blendedItems[] = $formattedInterest[$iIdx++];
                }
                if ($dIdx < $dCount) {
                    $blendedItems[] = $formattedDiverse[$dIdx++];
                }
            }

            $totalApprox = $totalInterest + $totalDiverse;
            $lastPage = (int) max(1, (int) ceil($totalApprox / $perPage));

            return [
                'items' => $blendedItems,
                'meta' => [
                    'current_page' => $page,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $totalApprox,
                    'is_cold_start' => $ragProfile['is_cold_start'],
                    'interaction_count' => $ragProfile['interaction_count'],
                    'personalization_distribution' => [
                        'interest_percentage' => 80,
                        'diversity_percentage' => 20,
                        'interest_items_count' => count($formattedInterest),
                        'diverse_items_count' => count($formattedDiverse),
                    ],
                ],
                'filters' => [
                    'category' => 'all',
                    'sort' => $sort,
                    'q' => null,
                ],
            ];
        }

        // Standard Filtered Query
        $query = Article::whereNotIn('id', $hiddenIds)
            ->with(['source:id,name,credibility_score,url', 'categories:id,name,slug,color', 'subcategories:id,name,slug', 'companies:id,name,slug,ticker']);

        // Category filter
        if (!empty($category) && $category !== 'all' && $category !== 'All Intelligence') {
            if ($category === 'breaking') {
                $query->where('is_breaking', true);
            } elseif ($category === 'companies' || $category === 'Companies') {
                $query->has('companies');
            } elseif ($category === 'executives' || $category === 'Executives') {
                $query->where(function ($q) {
                    $q->where('title', 'like', '%CEO%')
                      ->orWhere('title', 'like', '%Chief%')
                      ->orWhere('title', 'like', '%Executive%')
                      ->orWhere('title', 'like', '%CTO%')
                      ->orWhere('title', 'like', '%President%');
                });
            } else {
                $query->whereHas('categories', function ($q) use ($category) {
                    $q->where('slug', $category)
                      ->orWhere('name', 'like', "%{$category}%");
                });
            }
        }

        // Search query filter
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content_normalized', 'like', "%{$search}%");
            });
        }

        // Sorting
        switch ($sort) {
            case 'latest':
                $query->latest('published_at');
                break;
            case 'trending':
                $query->orderByDesc('is_breaking')
                      ->orderByDesc('importance_score')
                      ->latest('published_at');
                break;
            case 'credible':
                $query->whereIn('verification_status', ['confirmed', 'reported_reliable'])
                      ->orderByDesc('importance_score')
                      ->latest('published_at');
                break;
            case 'discussed':
            case 'relevance':
            default:
                $query->orderByDesc('importance_score')
                      ->latest('published_at');
                break;
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($paginator->items() as $article) {
            $item = $this->formatFeedItem($article, $user, null, $followedNames, $savedArticleIds);
            $item['match_type'] = 'filtered';
            $item['is_interest_match'] = true;
            $item['is_serendipity'] = false;
            $item['personalization_factor'] = 'Direct Category Query';
            $items[] = $item;
        }

        if ($sort === 'relevance') {
            usort($items, fn ($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);
        }

        return [
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'category' => $category ?? 'all',
                'sort' => $sort,
                'q' => $search,
            ],
        ];
    }

    /**
     * Format a unified, rich intelligence feed item.
     */
    public function formatFeedItem(
        Article $article,
        User $user,
        ?string $fallbackExplanation = null,
        ?array $followedNames = null,
        ?array $savedIds = null
    ): array {
        $followedNames = $followedNames ?? $this->collectFollowedEntityNames($user);
        $savedIds = $savedIds ?? $user->libraryItems()->where('item_type', 'article')->pluck('item_id')->toArray();

        $explanation = $fallbackExplanation ?? 'Verified institutional intelligence report';
        $relevanceScore = 70 + (int) (($article->importance_score ?? 5.0) * 2);

        $isMatchedFollow = false;
        foreach ($followedNames as $entName) {
            if (str_contains(strtolower($article->title), strtolower($entName)) ||
                str_contains(strtolower($article->content_normalized ?? ''), strtolower($entName))) {
                $explanation = "Because you follow {$entName}";
                $relevanceScore += 25;
                $isMatchedFollow = true;
                break;
            }
        }

        $rawText = strip_tags($article->content_normalized ?? $article->content_raw ?? '');
        $wordCount = max(10, str_word_count($rawText));
        $readingTime = max(1, (int) ceil($wordCount / 200));

        $category = $article->categories->first();
        $remoteImage = $article->featured_image ?? $article->hero_image ?? null;
        $sourceName = $article->source?->name ?? 'Wire Service';
        $attribution = $article->featured_image_caption ?: ($article->source ? "Source: {$sourceName}" : 'Source: Wire Service');

        return [
            'id' => $article->id,
            'type' => 'article',
            'headline' => $article->title,
            'title' => $article->title,
            'slug' => $article->slug,
            'summary' => substr($rawText, 0, 240) . (strlen($rawText) > 240 ? '...' : ''),
            'primary_image' => $remoteImage,
            'featured_image' => $remoteImage,
            'featured_image_caption' => $attribution,
            'source_attribution' => $attribution,
            'category' => $category ? [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'color' => $category->color ?? '#147A67',
            ] : [
                'name' => 'Strategic Technology',
                'slug' => 'strategic-technology',
                'color' => '#147A67',
            ],
            'subcategory' => $article->subcategories?->first() ? [
                'id' => $article->subcategories->first()->id,
                'name' => $article->subcategories->first()->name,
                'slug' => $article->subcategories->first()->slug,
            ] : null,
            'subcategories' => $article->relationLoaded('subcategories') && $article->subcategories
                ? $article->subcategories->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                ])->toArray()
                : [],
            'published_at' => $article->published_at?->toIso8601String(),
            'source' => [
                'name' => $sourceName,
                'credibility_score' => $article->source?->credibility_score ?? 9.2,
                'url' => $article->source?->url ?? null,
            ],
            'source_count' => max(1, ($article->relationLoaded('companies') && $article->companies ? $article->companies->count() : 0) + 1),
            'entities' => ($article->relationLoaded('companies') && $article->companies) ? $article->companies->pluck('name')->toArray() : [],
            'verification_status' => $article->verification_status ?? 'confirmed',
            'is_breaking' => (bool) $article->is_breaking,
            'is_trending' => (bool) ($article->is_breaking || ($article->importance_score ?? 0) >= 8.5),
            'relevance_score' => min(99, $relevanceScore),
            'reading_time_minutes' => $readingTime,
            'personalization_explanation' => $explanation,
            'is_saved' => in_array($article->id, $savedIds, true),
            'is_following' => $isMatchedFollow,
        ];
    }

    /**
     * Retrieve list of all entities followed by the subscriber.
     */
    public function getFollowingList(User $user): array
    {
        $entities = [];

        // Followed companies
        $followedCompanies = FollowedCompany::where('user_id', $user->id)
            ->with(['company' => function ($q) {
                $q->withCount('articles');
            }])
            ->get();

        foreach ($followedCompanies as $fc) {
            if ($fc->company) {
                $entities[] = [
                    'id' => $fc->company->id,
                    'type' => 'company',
                    'name' => $fc->company->name,
                    'slug' => $fc->company->slug,
                    'ticker' => $fc->company->ticker,
                    'industry' => $fc->company->sector ?? 'Technology',
                    'description' => $fc->company->description,
                    'is_followed' => true,
                    'stories_count' => $fc->company->articles_count ?? 0,
                ];
            }
        }

        // Followed executives
        $followedExecs = FollowedExecutive::where('user_id', $user->id)
            ->with('executive')
            ->get();

        foreach ($followedExecs as $fe) {
            if ($fe->executive) {
                $entities[] = [
                    'id' => $fe->executive->id,
                    'type' => 'executive',
                    'name' => $fe->executive->full_name,
                    'slug' => $fe->executive->slug,
                    'ticker' => null,
                    'industry' => $fe->executive->current_title ?? 'Executive Leadership',
                    'description' => $fe->executive->bio ?? '',
                    'is_followed' => true,
                    'stories_count' => 0,
                ];
            }
        }

        // Preferred topics / technologies from preferences
        $preferences = $user->preferences;
        if ($preferences && !empty($preferences->preferred_topics)) {
            foreach ((array) $preferences->preferred_topics as $topic) {
                $entities[] = [
                    'id' => 'topic-' . Str::slug($topic),
                    'type' => 'technology',
                    'name' => $topic,
                    'slug' => Str::slug($topic),
                    'ticker' => null,
                    'industry' => 'Strategic Technology',
                    'description' => "Monitored technology topic in your intelligence profile",
                    'is_followed' => true,
                    'stories_count' => 0,
                ];
            }
        }

        return $entities;
    }

    /**
     * Toggle follow state on an entity.
     */
    public function toggleFollowEntity(User $user, string $type, string $idOrSlug, ?string $name = null): array
    {
        $type = strtolower(trim($type));

        if ($type === 'company') {
            $company = Company::where('id', $idOrSlug)
                ->orWhere('slug', $idOrSlug)
                ->orWhere('name', $idOrSlug)
                ->first();

            if (! $company && !empty($name)) {
                $company = Company::firstOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name, 'is_active' => true]
                );
            }

            if (! $company) {
                throw new \InvalidArgumentException("Corporate entity '{$idOrSlug}' not found.");
            }

            $existing = FollowedCompany::where('user_id', $user->id)->where('company_id', $company->id)->first();
            if ($existing) {
                $existing->delete();
                return ['success' => true, 'is_followed' => false, 'message' => "Unfollowed {$company->name} successfully."];
            }

            FollowedCompany::create(['user_id' => $user->id, 'company_id' => $company->id, 'notify_on_events' => true]);
            return ['success' => true, 'is_followed' => true, 'message' => "Now following {$company->name}."];
        }

        if ($type === 'executive') {
            $exec = Executive::where('id', $idOrSlug)
                ->orWhere('slug', $idOrSlug)
                ->orWhere('full_name', $idOrSlug)
                ->first();

            if (! $exec && !empty($name)) {
                $exec = Executive::firstOrCreate(
                    ['slug' => Str::slug($name)],
                    ['full_name' => $name, 'is_active' => true]
                );
            }

            if (! $exec) {
                throw new \InvalidArgumentException("Executive '{$idOrSlug}' not found.");
            }

            $existing = FollowedExecutive::where('user_id', $user->id)->where('executive_id', $exec->id)->first();
            if ($existing) {
                $existing->delete();
                return ['success' => true, 'is_followed' => false, 'message' => "Unfollowed {$exec->full_name} successfully."];
            }

            FollowedExecutive::create(['user_id' => $user->id, 'executive_id' => $exec->id, 'notify_on_movement' => true]);
            return ['success' => true, 'is_followed' => true, 'message' => "Now following {$exec->full_name}."];
        }

        // Technology / Topic
        $topicName = $name ?: $idOrSlug;
        $pref = $user->preferences()->firstOrCreate(
            ['user_id' => $user->id],
            ['preferred_topics' => []]
        );

        $topics = (array) ($pref->preferred_topics ?? []);
        if (in_array($topicName, $topics, true)) {
            $topics = array_values(array_filter($topics, fn ($t) => $t !== $topicName));
            $pref->update(['preferred_topics' => $topics]);
            return ['success' => true, 'is_followed' => false, 'message' => "Unfollowed {$topicName}."];
        }

        $topics[] = $topicName;
        $pref->update(['preferred_topics' => array_unique($topics)]);
        return ['success' => true, 'is_followed' => true, 'message' => "Now following {$topicName}."];
    }

    /**
     * Get suggested entities for the user to follow.
     */
    public function getFollowingSuggestions(User $user): array
    {
        $followedCompanyIds = FollowedCompany::where('user_id', $user->id)->pluck('company_id')->toArray();

        $companies = Company::whereNotIn('id', $followedCompanyIds)
            ->where('is_active', true)
            ->withCount('articles')
            ->orderByDesc('articles_count')
            ->limit(6)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'type' => 'company',
                'name' => $c->name,
                'slug' => $c->slug,
                'ticker' => $c->ticker,
                'industry' => $c->sector ?? 'Semiconductor & Cloud',
                'description' => $c->description ?? 'Global technology leader',
                'is_followed' => false,
                'stories_count' => $c->articles_count,
            ])
            ->toArray();

        $topics = [
            ['id' => 'topic-genai', 'type' => 'technology', 'name' => 'Generative AI Infrastructure', 'slug' => 'generative-ai', 'industry' => 'Artificial Intelligence', 'is_followed' => false],
            ['id' => 'topic-litho', 'type' => 'technology', 'name' => 'High-NA EUV Lithography', 'slug' => 'euv-lithography', 'industry' => 'Semiconductor Fabrication', 'is_followed' => false],
            ['id' => 'topic-quantum', 'type' => 'technology', 'name' => 'Quantum Compute & Cryptography', 'slug' => 'quantum-computing', 'industry' => 'Emerging Technology', 'is_followed' => false],
            ['id' => 'topic-chipsact', 'type' => 'topic', 'name' => 'Sovereign Chips Acts & State Aid', 'slug' => 'chips-acts', 'industry' => 'Policy & Geopolitics', 'is_followed' => false],
        ];

        return [
            'companies' => $companies,
            'technologies' => $topics,
        ];
    }

    /**
     * Get intelligence feed specifically filtered to followed entities.
     */
    public function getFollowingFeed(User $user, array $filters = []): array
    {
        $followedNames = $this->collectFollowedEntityNames($user);
        if (empty($followedNames)) {
            return [
                'items' => [],
                'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1, 'per_page' => 15],
                'message' => 'Follow companies, executives, or technologies to view dedicated updates.',
            ];
        }

        $filters['search'] = null;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(50, max(5, (int) ($filters['per_page'] ?? 15)));

        $query = Article::where(function ($q) use ($followedNames) {
            foreach ($followedNames as $name) {
                $q->orWhere('title', 'like', "%{$name}%")
                  ->orWhere('content_normalized', 'like', "%{$name}%");
            }
        })
        ->with(['source:id,name,credibility_score', 'categories:id,name,slug,color', 'companies:id,name,ticker'])
        ->latest('published_at');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $savedIds = $user->libraryItems()->where('item_type', 'article')->pluck('item_id')->toArray();

        $items = [];
        foreach ($paginator->items() as $art) {
            $items[] = $this->formatFeedItem($art, $user, 'Direct development in followed portfolio', $followedNames, $savedIds);
        }

        return [
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
