<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\ArticleSource;
use App\Models\Category;
use App\Models\DiscoveredStory;
use App\Models\ExtractedFact;
use App\Models\NewsSource;
use App\Models\PipelineJob;
use App\Models\StoryCluster;
use App\Models\Topic;
use App\Models\User;
use App\Services\Ingestion\ContentExtractorService;
use App\Services\Ingestion\DeduplicationService;
use App\Services\Ingestion\DTO\FeedItemDTO;
use App\Services\Ingestion\EvidenceVerificationService;
use App\Services\Ingestion\FeedCollectorService;
use App\Services\Ingestion\IntelligencePipelineCoordinator;
use App\Services\Ingestion\StoryClusteringService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntelligenceIngestionPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected NewsSource $source1;
    protected NewsSource $source2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@nexovant.si')->firstOrFail();

        $this->source1 = NewsSource::create([
            'name' => 'Reuters Technology Wire',
            'slug' => 'reuters-technology-wire',
            'url' => 'https://feeds.reuters.example.com/tech.xml',
            'feed_url' => 'https://feeds.reuters.example.com/tech.xml',
            'source_type' => 'RSS',
            'is_active' => true,
            'credibility_score' => 0.95,
            'trust_tier' => 'TIER_1',
            'source_category' => 'wire',
            'geographic_region' => 'GLOBAL',
            'fetch_interval_minutes' => 15,
        ]);

        $this->source2 = NewsSource::create([
            'name' => 'Bloomberg Technology Wire',
            'slug' => 'bloomberg-technology-wire',
            'url' => 'https://feeds.bloomberg.example.com/tech.xml',
            'feed_url' => 'https://feeds.bloomberg.example.com/tech.xml',
            'source_type' => 'ATOM',
            'is_active' => true,
            'credibility_score' => 0.92,
            'trust_tier' => 'TIER_1',
            'source_category' => 'wire',
            'geographic_region' => 'GLOBAL',
            'fetch_interval_minutes' => 15,
        ]);
    }

    /**
     * Test RSS 2.0 and Atom XML Parsing.
     */
    public function test_feed_collector_parses_rss2_and_atom_xml(): void
    {
        $rss2Xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Reuters Tech</title>
    <link>https://reuters.example.com</link>
    <description>Tech news</description>
    <item>
      <title>Quantum Computing Breakthrough Announced at Global Summit - Reuters</title>
      <link>https://reuters.example.com/article/quantum-summit?utm_source=rss&amp;utm_medium=feed</link>
      <description><![CDATA[<p>Researchers have achieved 1,000 logical qubits with 99.9% fidelity.</p>]]></description>
      <pubDate>Fri, 14 Nov 2026 12:00:00 GMT</pubDate>
      <guid>reuters-quantum-2026-101</guid>
      <author>Jane Doe</author>
    </item>
  </channel>
</rss>
XML;

        $atomXml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Bloomberg Tech</title>
  <link href="https://bloomberg.example.com"/>
  <updated>2026-11-14T12:00:00Z</updated>
  <entry>
    <title>Quantum Milestone Confirmed with 1,000 Qubits at Summit - Bloomberg</title>
    <link rel="alternate" href="https://bloomberg.example.com/news/quantum-summit?ref=rss&amp;utm_campaign=daily"/>
    <id>tag:bloomberg.com,2026:quantum-101</id>
    <published>2026-11-14T12:05:00Z</published>
    <summary>Independent verification confirms 1,000 logical qubits milestone.</summary>
    <content type="html"><![CDATA[<p>Independent verification confirms 1,000 logical qubits milestone achieved by summit team.</p>]]></content>
  </entry>
</feed>
XML;

        $collector = app(FeedCollectorService::class);

        $rssItems = $collector->parseFeedXml($rss2Xml, $this->source1);
        $this->assertCount(1, $rssItems);
        $this->assertInstanceOf(FeedItemDTO::class, $rssItems[0]);
        $this->assertStringContainsString('Quantum Computing Breakthrough', $rssItems[0]->title);
        $this->assertEquals('reuters-quantum-2026-101', $rssItems[0]->guid);

        $atomItems = $collector->parseFeedXml($atomXml, $this->source2);
        $this->assertCount(1, $atomItems);
        $this->assertInstanceOf(FeedItemDTO::class, $atomItems[0]);
        $this->assertStringContainsString('Quantum Milestone Confirmed', $atomItems[0]->title);
        $this->assertEquals('tag:bloomberg.com,2026:quantum-101', $atomItems[0]->guid);
    }

    /**
     * Test SSRF Protection against loopback, private ranges, and cloud metadata.
     */
    public function test_ssrf_validator_blocks_internal_and_cloud_metadata_targets(): void
    {
        $collector = app(FeedCollectorService::class);

        $blockedUrls = [
            'http://127.0.0.1/rss.xml',
            'http://localhost:8000/feed',
            'http://169.254.169.254/latest/meta-data/',
            'http://192.168.1.10/admin/feed',
            'http://10.0.0.1/rss',
            'http://172.16.0.1/feed',
            'file:///etc/passwd',
            'gopher://127.0.0.1:6379/_flushall',
        ];

        foreach ($blockedUrls as $url) {
            $blocked = false;
            try {
                $collector->assertUrlSafeFromSSRF($url);
            } catch (\Throwable $e) {
                $blocked = true;
            }
            $this->assertTrue($blocked, "Expected SSRF protection to block: {$url}");
        }
    }

    /**
     * Test URL Canonicalization and Content Normalization.
     */
    public function test_content_extractor_canonicalizes_urls_and_normalizes_text(): void
    {
        $extractor = app(ContentExtractorService::class);

        $dirtyUrl = 'HTTPS://Reuters.Example.com:443/article/quantum-summit/?utm_source=twitter&utm_medium=social&utm_campaign=tech#top-section';
        $canonical = $extractor->canonicalizeUrl($dirtyUrl);
        $this->assertEquals('https://reuters.example.com/article/quantum-summit', $canonical);

        $dirtyTitle = 'Quantum Computing Breakthrough Announced at Global Summit - Reuters Technology Wire';
        $normalizedTitle = $extractor->normalizeTitle($dirtyTitle);
        $this->assertEquals('quantum computing breakthrough announced at global summit', $normalizedTitle);

        $html = '<script>alert("xss")</script><p>Clean <b>paragraph</b> text.</p><style>body{color:red;}</style>';
        $sanitized = $extractor->sanitizeContent($html);
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringNotContainsString('<style>', $sanitized);
        $this->assertStringContainsString('Clean', $sanitized);

        $hash = $extractor->generateContentHash($canonical, $normalizedTitle);
        $this->assertEquals(64, strlen($hash));
    }

    /**
     * Test Deduplication Service for exact URL, content hash, and fuzzy title matching.
     */
    public function test_deduplication_service_detects_duplicates(): void
    {
        $dedup = app(DeduplicationService::class);

        // First story
        $story1 = DiscoveredStory::create([
            'source_id' => $this->source1->id,
            'title' => 'Nvidia Unveils Next Generation AI Architecture Blackwell Ultra',
            'canonical_url' => 'https://reuters.example.com/nvidia-blackwell-ultra',
            'content_hash' => hash('sha256', 'https://reuters.example.com/nvidia-blackwell-ultra|Nvidia Unveils Next Generation AI Architecture Blackwell Ultra'),
            'processing_status' => 'pending',
            'published_at' => now(),
            'discovered_at' => now(),
        ]);

        // Same URL should duplicate
        $dupByUrl = $dedup->checkDuplicate($story1->canonical_url, 'Different Headline', $story1->content_hash);
        $this->assertNotNull($dupByUrl);
        $this->assertEquals($story1->id, $dupByUrl->id);

        // Same content hash should duplicate
        $dupByHash = $dedup->checkDuplicate('https://other.com/story', 'Different Headline', $story1->content_hash);
        $this->assertNotNull($dupByHash);
        $this->assertEquals($story1->id, $dupByHash->id);

        // Fuzzy match on near-identical title within 72 hours
        $dupByFuzzy = $dedup->checkDuplicate(
            'https://different-source.com/article/nvidia-ultra-ai',
            'Nvidia Unveils Next Generation AI Architecture Blackwell Ultra - Wire Report',
            hash('sha256', 'unique-hash')
        );
        $this->assertNotNull($dupByFuzzy);
        $this->assertEquals($story1->id, $dupByFuzzy->id);
    }

    /**
     * Test Story Clustering, Cluster Merging, and Cluster Splitting.
     */
    public function test_story_clustering_and_cluster_merge_and_split(): void
    {
        $clustering = app(StoryClusteringService::class);

        $story1 = DiscoveredStory::create([
            'source_id' => $this->source1->id,
            'title' => 'OpenAI Releases GPT-5 Frontier Model with Native Reasoning',
            'canonical_url' => 'https://reuters.example.com/openai-gpt5',
            'content_hash' => hash('sha256', 'openai-gpt5'),
            'summary' => 'OpenAI announced GPT-5 with autonomous reasoning and multimodal agency.',
            'processing_status' => 'pending',
            'published_at' => now(),
            'discovered_at' => now(),
        ]);

        $cluster1 = $clustering->assignStoryToCluster($story1);
        $this->assertInstanceOf(StoryCluster::class, $cluster1);
        $this->assertEquals(1, $cluster1->story_count);

        // Second story on same topic should assign to same cluster
        $story2 = DiscoveredStory::create([
            'source_id' => $this->source2->id,
            'title' => 'OpenAI GPT-5 Frontier Model Debuts with Multimodal Reasoning',
            'canonical_url' => 'https://bloomberg.example.com/openai-gpt5-debut',
            'content_hash' => hash('sha256', 'openai-gpt5-debut'),
            'summary' => 'OpenAI has released its latest frontier model named GPT-5.',
            'processing_status' => 'pending',
            'published_at' => now(),
            'discovered_at' => now(),
        ]);

        $cluster2 = $clustering->assignStoryToCluster($story2);
        $this->assertEquals($cluster1->id, $cluster2->id);
        $this->assertEquals(2, $cluster1->fresh()->story_count);

        // Separate story on completely different topic forms a new cluster
        $story3 = DiscoveredStory::create([
            'source_id' => $this->source1->id,
            'title' => 'Tesla Unveils Full Self Driving Hardware Version 5',
            'canonical_url' => 'https://reuters.example.com/tesla-hw5',
            'content_hash' => hash('sha256', 'tesla-hw5'),
            'summary' => 'Tesla announced HW5 automotive computing platform.',
            'processing_status' => 'pending',
            'published_at' => now(),
            'discovered_at' => now(),
        ]);
        $clusterIndependent = $clustering->assignStoryToCluster($story3);
        $this->assertNotEquals($cluster1->id, $clusterIndependent->id);

        // Test Cluster Merge
        $mergedCluster = $clustering->mergeClusters($cluster1->id, $clusterIndependent->id, 'Consolidating emerging tech dispatches');
        $this->assertEquals(3, $mergedCluster->fresh()->story_count);
        $this->assertDatabaseMissing('story_clusters', ['id' => $clusterIndependent->id]);

        // Test Cluster Split
        $newSplitCluster = $clustering->splitCluster($cluster1->id, [$story3->id], 'Splitting automotive track from AI model track');
        $this->assertInstanceOf(StoryCluster::class, $newSplitCluster);
        $this->assertNotEquals($cluster1->id, $newSplitCluster->id);
        $this->assertEquals(2, $cluster1->fresh()->story_count);
        $this->assertEquals(1, $newSplitCluster->fresh()->story_count);
    }

    /**
     * Test Evidence Verification Service cross-referencing facts and flagging consensus vs conflicts.
     */
    public function test_evidence_verification_flags_consensus_and_conflicts(): void
    {
        $clustering = app(StoryClusteringService::class);
        $verification = app(EvidenceVerificationService::class);

        $story1 = DiscoveredStory::create([
            'source_id' => $this->source1->id,
            'title' => 'Apple Acquires Robotics Startup Autonomous AI for $1.5 Billion',
            'canonical_url' => 'https://reuters.example.com/apple-robotics',
            'content_hash' => hash('sha256', 'apple-robotics-1'),
            'body_text' => 'Apple has agreed to acquire robotics startup Autonomous AI for $1.5 billion in cash.',
            'processing_status' => 'pending',
            'published_at' => now(),
            'discovered_at' => now(),
        ]);

        $story2 = DiscoveredStory::create([
            'source_id' => $this->source2->id,
            'title' => 'Apple Buys Autonomous AI for $1.5 Billion in Robotics Push',
            'canonical_url' => 'https://bloomberg.example.com/apple-robotics',
            'content_hash' => hash('sha256', 'apple-robotics-2'),
            'body_text' => 'In a major expansion into physical AI, Apple acquired Autonomous AI for $1.5 billion.',
            'processing_status' => 'pending',
            'published_at' => now(),
            'discovered_at' => now(),
        ]);

        $cluster = $clustering->assignStoryToCluster($story1);
        $clustering->assignStoryToCluster($story2);

        $verification->extractAndVerifyFactsForCluster($cluster);

        $facts = ExtractedFact::where('cluster_id', $cluster->id)->get();
        $this->assertNotEmpty($facts);

        // Check if multi-source supported consensus was recognized
        $consensusFacts = $facts->where('verification_status', 'supported_multiple');
        $this->assertNotEmpty($consensusFacts);
    }

    /**
     * Test Intelligence Pipeline Coordinator respects all 5 Manual Override Locks.
     */
    public function test_pipeline_coordinator_strictly_respects_5_manual_locks(): void
    {
        $categoryOriginal = Category::firstOrFail();
        $categoryProtected = Category::where('id', '!=', $categoryOriginal->id)->first() ?? Category::create([
            'name' => 'Protected Custom Category',
            'slug' => 'protected-custom-category',
            'is_active' => true,
        ]);

        $topicOriginal = Topic::first();

        // Create an existing article with all 5 manual locks enabled
        $cluster = StoryCluster::create([
            'headline' => 'Google DeepMind Unveils Next Era Gemini 3.0 Reasoning Engine',
            'summary' => 'Gemini 3.0 introduces real-time multimodal test-time compute scaling.',
            'story_count' => 1,
            'confidence_score' => 0.95,
        ]);

        $story = DiscoveredStory::create([
            'source_id' => $this->source1->id,
            'cluster_id' => $cluster->id,
            'title' => 'Google DeepMind Unveils Next Era Gemini 3.0',
            'canonical_url' => 'https://reuters.example.com/gemini-3-0',
            'content_hash' => hash('sha256', 'gemini-3-0'),
            'processing_status' => 'clustered',
            'published_at' => now(),
            'discovered_at' => now(),
        ]);
        $cluster->stories()->attach($story->id, ['is_primary' => true]);

        $article = Article::create([
            'source_id' => $this->source1->id,
            'title' => 'MANUAL LOCKED HEADLINE: DeepMind Gemini 3.0 Master Analysis',
            'slug' => 'manual-locked-headline-deepmind-gemini-30-master-analysis',
            'canonical_url' => 'https://reuters.example.com/gemini-3-0',
            'content_raw' => '<h1>MANUAL LOCKED BODY</h1><p>This prose was hand-crafted by human editor and must NEVER be overwritten.</p>',
            'content_normalized' => '<h1>MANUAL LOCKED BODY</h1><p>This prose was hand-crafted by human editor and must NEVER be overwritten.</p>',
            'featured_image' => 'https://cdn.nexovant.si/curated-editor-cover.jpg',
            'published_at' => now(),
            'status' => 'PUBLISHED',
            'is_editorial_approved' => true,
            // 5 Manual Locks all set to true
            'lock_content' => true,
            'lock_category' => true,
            'lock_topic' => true,
            'lock_image' => true,
            'lock_tags' => true,
        ]);

        $article->categories()->sync([$categoryProtected->id]);
        if ($topicOriginal) {
            $article->topics()->sync([$topicOriginal->id]);
        }
        $tag1 = \App\Models\Tag::firstOrCreate(['slug' => 'editorial-exclusive'], ['name' => 'editorial-exclusive']);
        $tag2 = \App\Models\Tag::firstOrCreate(['slug' => 'locked-tag'], ['name' => 'locked-tag']);
        $article->tags()->sync([$tag1->id, $tag2->id]);

        \App\Models\ArticleSummary::create([
            'article_id' => $article->id,
            'executive_summary' => 'MANUAL LOCKED SUMMARY: Exclusive editor review of Gemini 3.0 architecture.',
            'model_version' => 'v1',
        ]);

        $cluster->update(['article_id' => $article->id]);

        $coordinator = app(IntelligencePipelineCoordinator::class);

        // Run synthesis on the cluster
        $coordinator->synthesizeArticleForCluster($cluster);

        $article->refresh();

        // Verify that none of the 5 locked attributes were overwritten
        $this->assertEquals('MANUAL LOCKED HEADLINE: DeepMind Gemini 3.0 Master Analysis', $article->title);
        $this->assertEquals('MANUAL LOCKED SUMMARY: Exclusive editor review of Gemini 3.0 architecture.', $article->summary?->executive_summary);
        $this->assertStringContainsString('MANUAL LOCKED BODY', $article->content_raw);
        $this->assertTrue($article->categories->contains('id', $categoryProtected->id));
        $this->assertEquals('https://cdn.nexovant.si/curated-editor-cover.jpg', $article->featured_image);
        $this->assertTrue($article->tags->contains('slug', 'editorial-exclusive'));
    }

    /**
     * Test Intelligence Pipeline Coordinator protects UNPUBLISHED article status.
     */
    public function test_pipeline_coordinator_never_overwrites_unpublished_article_status(): void
    {
        $cluster = StoryCluster::create([
            'headline' => 'Semiconductor Fab Capacity Report 2026',
            'summary' => 'Global fab investments exceed $150 billion.',
            'story_count' => 1,
            'confidence_score' => 0.88,
        ]);

        $story = DiscoveredStory::create([
            'source_id' => $this->source1->id,
            'cluster_id' => $cluster->id,
            'title' => 'Semiconductor Fab Capacity Report 2026',
            'canonical_url' => 'https://reuters.example.com/fab-capacity',
            'content_hash' => hash('sha256', 'fab-capacity'),
            'processing_status' => 'clustered',
            'published_at' => now(),
            'discovered_at' => now(),
        ]);
        $cluster->stories()->attach($story->id, ['is_primary' => true]);

        $article = Article::create([
            'source_id' => $this->source1->id,
            'title' => 'Retracted or Deprecated Intelligence Report',
            'slug' => 'retracted-or-deprecated-intelligence-report',
            'canonical_url' => 'https://reuters.example.com/fab-capacity',
            'content_raw' => '<p>Article was pulled by editorial leadership due to legal embargo.</p>',
            'content_normalized' => '<p>Article was pulled by editorial leadership due to legal embargo.</p>',
            'published_at' => now(),
            'status' => 'UNPUBLISHED', // Specifically unpublished by human editor
            'is_editorial_approved' => false,
            'lock_content' => false,
        ]);

        $cluster->update(['article_id' => $article->id]);

        $coordinator = app(IntelligencePipelineCoordinator::class);
        $coordinator->synthesizeArticleForCluster($cluster);

        $article->refresh();

        // Must remain UNPUBLISHED and must not revert to PUBLISHED or REVIEW
        $this->assertEquals('UNPUBLISHED', $article->status);
    }

    /**
     * Test Idempotency and Article Sources / Revision Tracking.
     */
    public function test_pipeline_coordinator_is_idempotent_and_tracks_sources_and_revisions(): void
    {
        $cluster = StoryCluster::create([
            'headline' => 'Next-Gen Solid State Battery Begins Production Deployment',
            'summary' => 'Solid state cells achieve 450 Wh/kg energy density.',
            'story_count' => 1,
            'confidence_score' => 0.90,
        ]);

        $story = DiscoveredStory::create([
            'source_id' => $this->source1->id,
            'cluster_id' => $cluster->id,
            'title' => 'Solid State Battery Begins Pilot Manufacturing',
            'canonical_url' => 'https://reuters.example.com/solid-state-battery',
            'content_hash' => hash('sha256', 'solid-state-battery'),
            'processing_status' => 'clustered',
            'published_at' => now(),
            'discovered_at' => now(),
        ]);
        $cluster->stories()->attach($story->id, ['is_primary' => true]);

        $coordinator = app(IntelligencePipelineCoordinator::class);

        // Run cycle 1
        $article1 = $coordinator->synthesizeArticleForCluster($cluster);
        $this->assertInstanceOf(Article::class, $article1);
        $initialArticleCount = Article::count();

        // Run cycle 2 (idempotent re-run)
        $article2 = $coordinator->synthesizeArticleForCluster($cluster);
        $this->assertEquals($article1->id, $article2->id);
        $this->assertEquals($initialArticleCount, Article::count(), 'Idempotent re-run must not create duplicate articles');

        // Check article provenance sources
        $sources = ArticleSource::where('article_id', $article1->id)->get();
        $this->assertNotEmpty($sources);
        $this->assertEquals($this->source1->id, $sources->first()->source_id);

        // Check article revision history
        $revisions = ArticleRevision::where('article_id', $article1->id)->get();
        $this->assertNotEmpty($revisions);
    }

    /**
     * Test Admin Intelligence API endpoints.
     */
    public function test_admin_intelligence_api_endpoints(): void
    {
        // 1. Dashboard metrics
        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/intelligence');
        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'metrics' => [
                        'total_discovered',
                        'pending_processing',
                        'active_clusters',
                        'verified_facts',
                        'active_sources',
                    ],
                    'pipeline_summary',
                ],
            ]);

        // 2. Ingestion sources
        $resSources = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/intelligence/ingestion');
        $resSources->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['sources']]);

        // 3. Trigger Ingestion API
        $resTrigger = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/intelligence/ingestion/trigger', [
                'source_id' => $this->source1->id,
            ]);
        $resTrigger->assertStatus(200)
            ->assertJson(['success' => true]);

        // 4. Toggle Pause API
        $resPause = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/intelligence/ingestion/toggle-pause', [
                'is_paused' => true,
                'reason' => 'Maintenance window testing',
            ]);
        $resPause->assertStatus(200)
            ->assertJson(['success' => true, 'is_paused' => true]);

        // 5. Discoveries list API
        $resDiscoveries = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/intelligence/discoveries');
        $resDiscoveries->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);

        // 6. Automation Settings GET & PUT
        $resSettings = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/intelligence/automation');
        $resSettings->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['publishing_mode', 'min_source_credibility']]);

        $resUpdateSettings = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/admin/intelligence/automation', [
                'publishing_mode' => 'FULL_AUTO',
                'min_source_credibility' => 0.85,
                'min_fact_consensus_sources' => 3,
                'feed_fetch_interval_minutes' => 30,
            ]);
        $resUpdateSettings->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertEquals('FULL_AUTO', $resUpdateSettings->json('data.publishing_mode'));
    }
}
