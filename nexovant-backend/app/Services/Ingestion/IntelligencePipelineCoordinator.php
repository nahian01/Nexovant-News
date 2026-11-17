<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\ArticleSource;
use App\Models\ArticleSummary;
use App\Models\Category;
use App\Models\DiscoveredStory;
use App\Models\NewsSource;
use App\Models\PipelineJob;
use App\Models\StoryCluster;
use App\Models\Subcategory;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use App\Services\Editorial\ArticleGenerationService;
use App\Services\Taxonomy\AutoTaggingService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class IntelligencePipelineCoordinator
{
    public function __construct(
        protected FeedCollectorService $feedCollector,
        protected ContentExtractorService $contentExtractor,
        protected DeduplicationService $deduplicationService,
        protected StoryClusteringService $clusteringService,
        protected EvidenceVerificationService $verificationService,
        protected ArticleGenerationService $articleGenerator,
        protected AutoTaggingService $autoTaggingService
    ) {
    }

    /**
     * Run full ingestion pipeline for an active NewsSource.
     *
     * @return array{source_id: string, items_fetched: int, stories_created: int, duplicates_found: int, clusters_updated: int, articles_generated: int}
     */
    public function processSource(NewsSource $source, ?User $initiator = null): array
    {
        $startTime = microtime(true);

        $job = PipelineJob::create([
            'source_id' => $source->id,
            'stage' => '1. Source Discovery',
            'status' => 'processing',
            'item_title' => "Feed Ingestion: {$source->name}",
            'item_url' => $source->feed_url ?: $source->url,
            'started_at' => now(),
        ]);

        $stats = [
            'source_id' => $source->id,
            'items_fetched' => 0,
            'stories_created' => 0,
            'duplicates_found' => 0,
            'clusters_updated' => 0,
            'articles_generated' => 0,
        ];

        try {
            // Stage 1 & 2: Fetch feed items with SSRF validation
            $feedResult = $this->feedCollector->fetchSourceFeed($source);
            $feedItems = $feedResult['items'];
            $stats['items_fetched'] = count($feedItems);

            if ($feedResult['not_modified']) {
                $job->update([
                    'stage' => '14. Publishing',
                    'status' => 'completed',
                    'duration_seconds' => round(microtime(true) - $startTime, 2),
                    'completed_at' => now(),
                    'error_message' => 'Feed returned HTTP 304 Not Modified. No new content.',
                ]);
                return $stats;
            }

            // Stage 3 & 4: Normalization & Content Extraction
            $job->update(['stage' => '2. Extraction']);
            $discoveredStories = [];

            foreach ($feedItems as $item) {
                $story = $this->contentExtractor->extractAndStore($item, $source);
                $stats['stories_created']++;

                // Stage 5: Deduplication
                $dupCheck = $this->deduplicationService->detectDuplicate($story);
                if ($dupCheck['is_duplicate'] && $dupCheck['duplicate_of']) {
                    $this->deduplicationService->markAsDuplicate(
                        $story,
                        $dupCheck['duplicate_of'],
                        $dupCheck['match_type'] ?? 'unknown',
                        $dupCheck['similarity_score']
                    );
                    $stats['duplicates_found']++;
                } else {
                    $discoveredStories[] = $story;
                }
            }

            // Stage 6: Story Clustering
            $job->update(['stage' => '7. Story Clustering']);
            $affectedClusters = [];

            foreach ($discoveredStories as $story) {
                $cluster = $this->clusteringService->clusterStory($story);
                $affectedClusters[$cluster->id] = $cluster;
                $stats['clusters_updated']++;
            }

            // Stage 7-13: Process each cluster through Fact Extraction, Synthesis & Editorial Routing
            foreach ($affectedClusters as $cluster) {
                $this->processClusterPipeline($cluster, $job);
                $stats['articles_generated']++;
            }

            $job->update([
                'stage' => '14. Publishing',
                'status' => 'completed',
                'duration_seconds' => round(microtime(true) - $startTime, 2),
                'completed_at' => now(),
            ]);

            return $stats;
        } catch (Throwable $e) {
            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_seconds' => round(microtime(true) - $startTime, 2),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Alias for processClusterPipeline for testing and external consumers.
     */
    public function synthesizeArticleForCluster(StoryCluster $cluster, ?PipelineJob $job = null): ?Article
    {
        return $this->processClusterPipeline($cluster, $job);
    }

    /**
     * Process an individual StoryCluster through the cognitive extraction, synthesis, and editorial routing stages.
     */
    public function processClusterPipeline(StoryCluster $cluster, ?PipelineJob $job = null): ?Article
    {
        $clusterStories = $cluster->stories()->with('source')->get();
        if ($clusterStories->isEmpty()) {
            return null;
        }

        // 1. Stage: Fact Extraction & Multi-Source Comparison
        if ($job) {
            $job->update(['stage' => '8. Verification', 'cluster_id' => $cluster->id]);
        }

        $facts = $this->verificationService->extractAndVerifyClusterFacts($cluster);

        // 2. Stage: Taxonomy & Entity Classification
        if ($job) {
            $job->update(['stage' => '4. Classification']);
        }

        $leadStory = $clusterStories->firstWhere('pivot.is_primary', true) ?: $clusterStories->first();
        $textToClassify = $cluster->title . "\n" . ($cluster->summary ?: $leadStory->summary ?: '');
        $classification = $this->autoTaggingService->classifyStory($textToClassify);

        // 3. Stage: Multi-Source Intelligence Synthesis
        if ($job) {
            $job->update(['stage' => '10. Article Generation']);
        }

        $sourceInputs = $clusterStories->map(fn ($s) => [
            'title' => $s->title,
            'content' => $s->content_normalized ?: $s->summary ?: $s->title,
            'source_name' => $s->source?->name ?? 'Wire',
        ])->toArray();

        $synthesis = $this->articleGenerator->synthesizeArticleFromSources(
            sources: $sourceInputs,
            topic: $cluster->title
        );

        $seo = $this->articleGenerator->generateSeoMetadata($synthesis['title'], $synthesis['body']);

        // 4. Stage: Editorial Routing & Protection Policy
        if ($job) {
            $job->update(['stage' => '13. Editorial Validation']);
        }

        $article = $this->routeEditorialArticle(
            cluster: $cluster,
            leadStory: $leadStory,
            synthesis: $synthesis,
            seo: $seo,
            classification: $classification,
            clusterStories: $clusterStories
        );

        // Link article to cluster
        $cluster->update([
            'article_id' => $article->id,
            'status' => 'synthesized',
        ]);

        if ($job) {
            $job->update([
                'article_id' => $article->id,
                'extracted_facts' => array_map(fn ($f) => ['claim' => $f->claim, 'status' => $f->verification_status], $facts),
            ]);
        }

        // Trigger real automated intelligence monitoring & early-warning pipeline
        try {
            \App\Jobs\ProcessIngestedArticleIntelligenceJob::dispatch($article->id);
        } catch (\Throwable $e) {
            Log::warning("Failed to queue ProcessIngestedArticleIntelligenceJob for article [{$article->id}]: " . $e->getMessage());
        }

        return $article;
    }

    /**
     * Create or update Article following strict editorial lock protection and publishing policies.
     *
     * @param array<string, mixed> $synthesis
     * @param array<string, mixed> $seo
     * @param array<string, mixed> $classification
     * @param \Illuminate\Support\Collection<int, DiscoveredStory> $clusterStories
     */
    protected function routeEditorialArticle(
        StoryCluster $cluster,
        DiscoveredStory $leadStory,
        array $synthesis,
        array $seo,
        array $classification,
        $clusterStories
    ): Article {
        return DB::transaction(function () use ($cluster, $leadStory, $synthesis, $seo, $classification, $clusterStories) {
            // Determine editorial publication policy from persistent settings
            $settingsPath = storage_path('app/settings.json');
            $settings = File::exists($settingsPath) ? (json_decode(File::get($settingsPath), true) ?? []) : [];
            $policyMode = strtolower((string) ($settings['publishing_mode'] ?? $settings['editorial_publication_mode'] ?? 'auto_review'));

            $targetStatus = match ($policyMode) {
                'full_auto' => 'PUBLISHED',
                'manual_only' => 'DRAFT',
                default => 'REVIEW',
            };

            // Check if cluster already has an existing article (Idempotency Protection)
            $existingArticle = $cluster->article_id
                ? Article::find($cluster->article_id)
                : null;

            if ($existingArticle) {
                // Ensure bidirectional link
                if (!$cluster->article_id || $cluster->article_id !== $existingArticle->id) {
                    $cluster->update(['article_id' => $existingArticle->id]);
                }

                // CRITICAL RULE 1: Never overwrite an article with status UNPUBLISHED
                if ($existingArticle->status === 'UNPUBLISHED') {
                    return $existingArticle;
                }

                // CRITICAL RULE 2: Respect all 5 manual override locks
                $updateData = [];

                if (!$existingArticle->lock_content) {
                    $updateData['title'] = $synthesis['title'];
                    $updateData['content_raw'] = $synthesis['body'];
                    $updateData['content_normalized'] = $synthesis['body'];

                    if (!empty($synthesis['summary'])) {
                        ArticleSummary::updateOrCreate(
                            ['article_id' => $existingArticle->id],
                            ['executive_summary' => $synthesis['summary'], 'model_version' => 'nexovant-central-router-v2']
                        );
                    }
                }

                if (!$existingArticle->lock_image && !empty($leadStory->featured_image)) {
                    $updateData['featured_image'] = $leadStory->featured_image;
                }

                if (!$existingArticle->lock_category && !empty($classification['category_slug'])) {
                    $categoryName = $classification['category_name'] ?? ucwords(str_replace('-', ' ', $classification['category_slug']));
                    $categorySlug = Str::slug($classification['category_slug']);
                    $cat = Category::firstOrCreate(
                        ['slug' => $categorySlug],
                        [
                            'name' => $categoryName,
                            'description' => "Intelligence and industry developments in {$categoryName}",
                            'color' => '#147A67',
                            'is_active' => true,
                        ]
                    );
                    $existingArticle->categories()->syncWithoutDetaching([$cat->id]);

                    if (!empty($classification['subcategory_name']) || !empty($classification['subcategory_slug'])) {
                        $subName = $classification['subcategory_name'] ?? ucwords(str_replace('-', ' ', $classification['subcategory_slug'] ?? 'General'));
                        $subSlug = Str::slug($classification['subcategory_slug'] ?? $subName);
                        $sub = Subcategory::firstOrCreate(
                            ['category_id' => $cat->id, 'slug' => $subSlug],
                            [
                                'name' => $subName,
                                'description' => "Subcategory for {$subName} under {$categoryName}",
                                'is_active' => true,
                            ]
                        );
                        $existingArticle->subcategories()->syncWithoutDetaching([$sub->id]);
                    }
                }

                if (!$existingArticle->lock_topic && !empty($classification['topics'])) {
                    $topicIds = [];
                    foreach ($classification['topics'] as $topicName) {
                        $top = Topic::firstOrCreate(['slug' => Str::slug($topicName)], ['name' => $topicName, 'is_active' => true]);
                        $topicIds[] = $top->id;
                    }
                    $existingArticle->topics()->sync($topicIds);
                }

                if (!$existingArticle->lock_tags && !empty($classification['tags'])) {
                    $tagIds = [];
                    foreach ($classification['tags'] as $tagName) {
                        $tag = Tag::firstOrCreate(['slug' => Str::slug($tagName)], ['name' => $tagName]);
                        $tagIds[] = $tag->id;
                    }
                    $existingArticle->tags()->sync($tagIds);
                }

                if (!empty($updateData)) {
                    $existingArticle->update($updateData);

                    // Create ArticleRevision
                    $nextVer = ((int) $existingArticle->revisions()->max('version_number')) + 1;
                    ArticleRevision::create([
                        'article_id' => $existingArticle->id,
                        'version_number' => $nextVer,
                        'title' => $existingArticle->title,
                        'content_raw' => $existingArticle->content_raw,
                        'content_normalized' => $existingArticle->content_normalized,
                        'summary' => $synthesis['summary'] ?? null,
                        'change_summary' => 'Automated cluster multi-source refresh',
                    ]);
                }

                // Update provenance sources
                $this->syncArticleSources($existingArticle, $clusterStories);
                return $existingArticle;
            }

            // Create new Article
            $slug = Str::slug(substr($synthesis['title'], 0, 80)) . '-' . Str::random(5);

            $article = Article::create([
                'source_id' => $leadStory->source_id ?? NewsSource::first()?->id,
                'title' => $synthesis['title'],
                'slug' => $slug,
                'canonical_url' => $leadStory->canonical_url,
                'content_raw' => $synthesis['body'],
                'content_normalized' => $synthesis['body'],
                'content_hash' => hash('sha256', $slug . '|' . $synthesis['title']),
                'published_at' => now(),
                'status' => $targetStatus,
                'verification_status' => 'reported_reliable',
                'importance_score' => 7.50,
                'featured_image' => $leadStory->featured_image,
                'featured_image_caption' => "Source attribution: {$leadStory->source?->name}",
                'meta_title' => $seo['meta_title'] ?? $synthesis['title'],
                'meta_description' => $seo['meta_description'] ?? substr($synthesis['summary'] ?? '', 0, 155),
                'meta_keywords' => !empty($seo['keywords']) ? implode(',', $seo['keywords']) : 'tech,ai',
            ]);

            // Associate article with cluster
            $cluster->update([
                'article_id' => $article->id,
                'status' => 'synthesized',
            ]);

            // Create executive summary record
            ArticleSummary::create([
                'article_id' => $article->id,
                'executive_summary' => $synthesis['summary'] ?? 'Synthesized executive summary.',
                'key_takeaways' => $synthesis['key_findings'] ?? [],
                'model_version' => 'nexovant-central-router-v2',
            ]);

            // Create initial Revision (Version 1)
            ArticleRevision::create([
                'article_id' => $article->id,
                'version_number' => 1,
                'title' => $article->title,
                'content_raw' => $article->content_raw,
                'content_normalized' => $article->content_normalized,
                'summary' => $synthesis['summary'] ?? null,
                'change_summary' => 'Initial automated intelligence synthesis',
            ]);

            // Attach taxonomy categories, subcategories, topics, and tags
            $categoryName = $classification['category_name'] ?? ucwords(str_replace('-', ' ', $classification['category_slug'] ?? 'artificial-intelligence'));
            $categorySlug = Str::slug($classification['category_slug'] ?? $categoryName);
            $category = Category::firstOrCreate(
                ['slug' => $categorySlug],
                [
                    'name' => $categoryName,
                    'description' => "Intelligence and industry developments in {$categoryName}",
                    'color' => '#147A67',
                    'is_active' => true,
                ]
            );
            $article->categories()->syncWithoutDetaching([$category->id]);

            if (!empty($classification['subcategory_name']) || !empty($classification['subcategory_slug'])) {
                $subName = $classification['subcategory_name'] ?? ucwords(str_replace('-', ' ', $classification['subcategory_slug'] ?? 'General'));
                $subSlug = Str::slug($classification['subcategory_slug'] ?? $subName);
                $subcategory = Subcategory::firstOrCreate(
                    ['category_id' => $category->id, 'slug' => $subSlug],
                    [
                        'name' => $subName,
                        'description' => "Subcategory for {$subName} under {$categoryName}",
                        'is_active' => true,
                    ]
                );
                $article->subcategories()->syncWithoutDetaching([$subcategory->id]);
            }

            if (!empty($classification['topics'])) {
                foreach ($classification['topics'] as $topicName) {
                    $topic = Topic::firstOrCreate(
                        ['slug' => Str::slug($topicName)],
                        ['name' => $topicName, 'is_active' => true]
                    );
                    $article->topics()->syncWithoutDetaching([$topic->id]);
                }
            }

            if (!empty($classification['tags'])) {
                foreach ($classification['tags'] as $tagName) {
                    $tag = Tag::firstOrCreate(
                        ['slug' => Str::slug($tagName)],
                        ['name' => $tagName]
                    );
                    $article->tags()->syncWithoutDetaching([$tag->id]);
                }
            }

            // Sync multi-source provenance
            $this->syncArticleSources($article, $clusterStories);

            return $article;
        });
    }

    /**
     * Attach provenance records for all source stories contributing to the synthesized article.
     *
     * @param \Illuminate\Support\Collection<int, DiscoveredStory> $clusterStories
     */
    protected function syncArticleSources(Article $article, $clusterStories): void
    {
        foreach ($clusterStories as $story) {
            ArticleSource::firstOrCreate(
                [
                    'article_id' => $article->id,
                    'story_id' => $story->id,
                ],
                [
                    'source_id' => $story->source_id,
                    'publisher_name' => $story->source?->publisher_name ?: $story->source?->name,
                    'source_url' => $story->canonical_url,
                    'source_published_at' => $story->published_at,
                ]
            );
        }
    }
}
