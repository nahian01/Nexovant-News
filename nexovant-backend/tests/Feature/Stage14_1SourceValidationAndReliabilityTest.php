<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessIngestedArticleIntelligenceJob;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\DiscoveredStory;
use App\Models\NewsSource;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Discovery\DTO\SearchQueryDTO;
use App\Services\Discovery\UnifiedSearchService;
use App\Services\Ingestion\Connectors\AcademicRepositoryConnector;
use App\Services\Ingestion\Connectors\GovernmentPublicDisclosureConnector;
use App\Services\Ingestion\Connectors\RestApiSourceConnector;
use App\Services\Ingestion\Connectors\RssAtomConnector;
use App\Services\Ingestion\Connectors\SourceConnectorManager;
use App\Services\Ingestion\DTO\FeedItemDTO;
use App\Services\Ingestion\DeduplicationService;
use App\Services\Ingestion\FeedCollectorService;
use App\Services\Multilingual\LanguageDetectionService;
use App\Services\Multilingual\TranslationService;
use App\Services\SourceNetwork\CoverageGapDetectionService;
use App\Services\SourceNetwork\GeographicCoverageService;
use App\Services\SourceNetwork\SourceDiversityService;
use App\Services\SourceNetwork\SourceReliabilityService;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Stage14_1SourceValidationAndReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $tenantUserA;
    protected User $tenantUserB;
    protected Organization $tenantA;
    protected Organization $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'account_type' => 'admin',
            'email' => 'admin.sources14_1@nexovant.test',
        ]);

        $this->tenantA = Organization::create([
            'name' => 'Apex Financial Intelligence',
            'slug' => 'apex-financial-intelligence',
            'is_active' => true,
        ]);

        $this->tenantB = Organization::create([
            'name' => 'Boreal Capital Group',
            'slug' => 'boreal-capital-group',
            'is_active' => true,
        ]);

        $this->tenantUserA = User::factory()->create([
            'account_type' => 'admin',
            'email' => 'analyst.a@apex.test',
        ]);
        OrganizationMember::create([
            'organization_id' => $this->tenantA->id,
            'user_id' => $this->tenantUserA->id,
            'role' => 'admin',
        ]);

        $this->tenantUserB = User::factory()->create([
            'account_type' => 'admin',
            'email' => 'analyst.b@boreal.test',
        ]);
        OrganizationMember::create([
            'organization_id' => $this->tenantB->id,
            'user_id' => $this->tenantUserB->id,
            'role' => 'admin',
        ]);
    }

    /**
     * GROUP A: Real-Source Connectors & Protocol Compliance.
     */
    public function test_group_a_real_source_connectors_and_protocol_compliance(): void
    {
        $rssSource = NewsSource::create([
            'name' => 'BBC World News RSS',
            'slug' => 'bbc-world-rss',
            'source_type' => 'rss',
            'website_url' => 'https://www.bbc.com/news',
            'feed_url' => 'https://feeds.bbci.co.uk/news/world/rss.xml',
            'canonical_domain' => 'bbc.com',
            'is_active' => true,
            'etag' => '"tag-initial-123"',
        ]);

        $rssXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>BBC News - World</title>
    <link>https://www.bbc.co.uk/news/world</link>
    <description>BBC News - World</description>
    <item>
      <title>Global Semiconductor Trade Rebounds in Q4</title>
      <link>https://www.bbc.co.uk/news/business-589021</link>
      <description>Semiconductor manufacturing outputs increased across East Asia.</description>
      <pubDate>Mon, 16 Nov 2026 12:00:00 GMT</pubDate>
      <guid>https://www.bbc.co.uk/news/business-589021</guid>
    </item>
  </channel>
</rss>
XML;

        $atomXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>arXiv.org q-fin updates</title>
  <entry>
    <title>High-Frequency Microstructure Analysis in Sovereign Debt Markets</title>
    <link href="https://arxiv.org/abs/2611.04519"/>
    <id>oai:arXiv.org:2611.04519</id>
    <summary>We quantify liquidity clustering around macroeconomic announcements.</summary>
    <published>2026-11-16T08:00:00Z</published>
    <author><name>Chen, L. and Weber, M.</name></author>
  </entry>
</feed>
XML;

        $govXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Bangladesh Bank Official Circulars</title>
    <link>https://www.bb.org.bd</link>
    <description>Official Circulars and Notices</description>
    <item>
      <title>BRPD Circular No. 12/2026: Prudential Guidelines on Foreign Exchange</title>
      <link>https://www.bb.org.bd/circulars/brpd-2026-12.pdf</link>
      <description>Guidelines issued under Section 45 of Bank Company Act.</description>
      <pubDate>Mon, 16 Nov 2026 09:00:00 GMT</pubDate>
      <guid>https://www.bb.org.bd/circulars/brpd-2026-12</guid>
    </item>
  </channel>
</rss>
XML;

        // Register all mock endpoints in unified sequence mapping
        Http::fake([
            'https://feeds.bbci.co.uk/*' => Http::sequence()
                ->push($rssXml, 200, ['ETag' => '"tag-new-456"'])
                ->push('', 304, ['ETag' => '"tag-new-456"']),
            'https://api.macro-intel.test/*' => function (\Illuminate\Http\Client\Request $request) {
                if ($request->header('Authorization')[0] !== 'Bearer sec_live_bearer_token_9981') {
                    return Http::response(['error' => 'Unauthorized'], 401);
                }
                return Http::response([
                    'status' => 'success',
                    'data' => [
                        [
                            'title' => 'Central European Inflation Drops to 2.1%',
                            'url' => 'https://macro-intel.test/reports/eu-inflation-nov-2026',
                            'summary' => 'Headline consumer price index cooled faster than projected.',
                            'published_at' => '2026-11-16T10:00:00Z',
                            'language' => 'en',
                        ]
                    ]
                ], 200);
            },
            'https://www.bb.org.bd/*' => Http::response($govXml, 200),
            'https://export.arxiv.org/*' => Http::response($atomXml, 200),
        ]);

        $connectorManager = app(SourceConnectorManager::class);
        $testResult = $connectorManager->testSource($rssSource);
        $this->assertTrue($testResult['success']);
        $this->assertEquals(200, $testResult['http_status']);
        $this->assertNotEmpty($testResult['sample_items']);
        $this->assertEquals('Global Semiconductor Trade Rebounds in Q4', $testResult['sample_items'][0]['title']);

        // Second fetch with conditional caching
        $rssSource->refresh();
        $fetchResult = $connectorManager->fetchSource($rssSource);
        $this->assertTrue($fetchResult['not_modified']);
        $this->assertEmpty($fetchResult['items']);

        // 2. Authorized REST API Connector with Bearer Token & Normalization
        $apiSource = NewsSource::create([
            'name' => 'World Economic Macro Indicators API',
            'slug' => 'world-macro-api',
            'source_type' => 'rest_api',
            'api_endpoint' => 'https://api.macro-intel.test/v1/indicators',
            'canonical_domain' => 'macro-intel.test',
            'auth_type' => 'bearer',
            'is_active' => true,
        ]);
        $apiSource->setEncryptedCredentialsAttribute(['token' => 'sec_live_bearer_token_9981']);
        $apiSource->save();

        $apiTest = $connectorManager->testSource($apiSource);
        $this->assertTrue($apiTest['success']);
        $this->assertEquals(200, $apiTest['http_status']);
        $this->assertCount(1, $apiTest['sample_items']);
        $this->assertEquals('Central European Inflation Drops to 2.1%', $apiTest['sample_items'][0]['title']);

        // 3. Government / Regulatory Public Disclosure Connector
        $govSource = NewsSource::create([
            'name' => 'Bangladesh Bank Official Gazette',
            'slug' => 'bangladesh-bank-gazette',
            'source_type' => 'regulatory',
            'feed_url' => 'https://www.bb.org.bd/rss/circulars.xml',
            'publisher_name' => 'Bangladesh Bank',
            'country_code' => 'BD',
            'is_active' => true,
        ]);

        $govConnector = app(GovernmentPublicDisclosureConnector::class);
        $this->assertTrue($govConnector->supports($govSource));
        $govResult = $govConnector->testConnection($govSource);
        $this->assertTrue($govResult['success']);
        $this->assertEquals('BRPD Circular No. 12/2026', $govResult['sample_items'][0]['official_citation']);

        // 4. Academic Repository Connector
        $academicSource = NewsSource::create([
            'name' => 'arXiv Quantitative Economics Archive',
            'slug' => 'arxiv-qfin',
            'source_type' => 'academic',
            'feed_url' => 'https://export.arxiv.org/rss/q-fin',
            'publisher_name' => 'arXiv.org',
            'is_active' => true,
        ]);

        $academicConnector = app(AcademicRepositoryConnector::class);
        $this->assertTrue($academicConnector->supports($academicSource));
        $acadResult = $academicConnector->testConnection($academicSource);
        $this->assertTrue($acadResult['success']);
        $this->assertNotEmpty($acadResult['sample_items']);
        $this->assertTrue($academicConnector->supports($academicSource));
        $acadResult = $academicConnector->testConnection($academicSource);
        $this->assertTrue($acadResult['success']);
        $this->assertNotEmpty($acadResult['sample_items']);
    }

    /**
     * GROUP B: Ingestion Lifecycle, Deduplication & Downstream Pipeline Verification.
     */
    public function test_group_b_complete_ingestion_lifecycle_and_intelligence_job_dispatch(): void
    {
        $source = NewsSource::create([
            'name' => 'Dhaka Financial Herald',
            'slug' => 'dhaka-fin-herald',
            'source_type' => 'rss',
            'canonical_domain' => 'dhakaherald.test',
            'is_active' => true,
        ]);

        $articleUrl = 'https://dhakaherald.test/articles/garment-exports-q3-surge';
        $itemDto = new FeedItemDTO(
            title: 'Garments Sector Logs 8.4% Export Surge in Q3',
            url: $articleUrl,
            summary: 'Readymade garments shipments to Europe and the Americas exceeded monthly targets.',
            contentRaw: 'Comprehensive trade customs statistics revealed substantial order recovery across knitwear and woven items.',
            author: 'Staff Correspondent',
            publishedAt: new DateTimeImmutable('-2 hours'),
            language: 'en',
            guid: 'dfh-article-7721'
        );

        // 1. Normalization & Storage preserving Canonical URL
        $article = Article::create([
            'source_id' => $source->id,
            'title' => $itemDto->title,
            'canonical_url' => $itemDto->url,
            'summary' => $itemDto->summary,
            'content_raw' => $itemDto->contentRaw,
            'content_normalized' => strip_tags($itemDto->contentRaw),
            'author' => $itemDto->author,
            'published_at' => $itemDto->publishedAt,
            'ingested_at' => now(),
            'verification_status' => 'verified',
            'status' => 'published',
        ]);

        $this->assertEquals($articleUrl, $article->canonical_url);
        $this->assertEquals($source->id, $article->source_id);
        $this->assertNotEmpty($article->content_hash);
        $this->assertNotEmpty($article->slug);

        // 2. Discovered Story & Deduplication Check
        $firstStory = DiscoveredStory::create([
            'source_id' => $source->id,
            'title' => $itemDto->title,
            'canonical_url' => $articleUrl,
            'content_hash' => hash('sha256', "{$articleUrl}|{$itemDto->title}"),
            'status' => 'discovered',
        ]);

        $dedupService = app(DeduplicationService::class);
        $secondStory = new DiscoveredStory([
            'source_id' => $source->id,
            'title' => $itemDto->title,
            'canonical_url' => $articleUrl,
        ]);
        $dupResult = $dedupService->detectDuplicate($secondStory);
        $this->assertTrue($dupResult['is_duplicate']);
        $this->assertEquals($firstStory->id, $dupResult['duplicate_of']?->id);

        // 3. Downstream Intelligence Processing Dispatch & Idempotency
        $job = new ProcessIngestedArticleIntelligenceJob($article->id);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->timeout);
        $this->assertEquals([10, 30, 60], $job->backoff);

        // First dispatch executes
        $job->handle(
            app(\App\Services\Automation\IntelligenceMonitoringService::class),
            app(\App\Services\Discovery\SavedSearchService::class)
        );

        // Verify idempotency lock behavior: active lock skips double-processing
        $lockKey = "job:lock:article_intelligence:{$article->id}";
        $lock = Cache::lock($lockKey, 60);
        $this->assertTrue($lock->get());
        $job->handle(
            app(\App\Services\Automation\IntelligenceMonitoringService::class),
            app(\App\Services\Discovery\SavedSearchService::class)
        );
        $lock->release();
    }

    /**
     * GROUP C: Multilingual Detection Benchmark & Translation Reliability.
     */
    public function test_group_c_multilingual_detection_benchmark_and_translation_reliability(): void
    {
        $detector = app(LanguageDetectionService::class);

        // 1. Manually reviewed evaluation benchmark dataset
        $benchmarkDataset = [
            // Bengali
            ['text' => 'বাংলাদেশ ব্যাংকের গভর্নর মুদ্রাস্ফীতি নিয়ন্ত্রণে কঠোর মুদ্রানীতি ঘোষণা করেছেন।', 'expected' => 'bn'],
            ['text' => 'ঢাকা স্টক এক্সচেঞ্জে মূল্যসূচক বৃদ্ধির সঙ্গে লেনদেনের পরিমাণ বৃদ্ধি পেয়েছে।', 'expected' => 'bn'],
            // English
            ['text' => 'The Federal Reserve signaled a data-dependent stance regarding benchmark interest rate adjustments.', 'expected' => 'en'],
            ['text' => 'Quarterly corporate earnings across semiconductor manufacturers surpassed consensus expectations.', 'expected' => 'en'],
            // French
            ['text' => "La Banque Centrale Européenne a maintenu ses taux directeurs pour stabiliser l'économie.", 'expected' => 'fr'],
            ['text' => 'Le gouvernement a annoncé un nouveau plan de relance pour la transition énergétique.', 'expected' => 'fr'],
            // German
            ['text' => 'Die Bundesbank warnt vor den Risiken einer anhaltend hohen Kerninflation im Euroraum.', 'expected' => 'de'],
            ['text' => 'Der deutsche Außenhandel verzeichnete im dritten Quartal einen leichten Zuwachs bei Exporten.', 'expected' => 'de'],
            // Spanish
            ['text' => 'El Banco de España proyecta un crecimiento moderado del producto interior bruto para el próximo año.', 'expected' => 'es'],
            ['text' => 'Los ministros de finanzas acordaron reforzar la supervisión del sector bancario.', 'expected' => 'es'],
            // Arabic
            ['text' => 'أعلن مصرف الإمارات المركزي عن قراره بالإبقاء على أسعار الفائدة الأساسية دون تغيير.', 'expected' => 'ar'],
            // Ambiguous / Edge cases
            ['text' => '', 'expected' => 'unknown'],
            ['text' => '12345 67890 @#$%^', 'expected' => 'unknown'],
            ['text' => 'xyz abc qwe', 'expected' => 'unknown'],
        ];

        $evalReport = $detector->evaluateDataset($benchmarkDataset);
        $this->assertGreaterThanOrEqual(90.0, $evalReport['accuracy']);
        $this->assertEquals(14, $evalReport['total_samples']);
        $this->assertGreaterThanOrEqual(3, $evalReport['ambiguous_handled']);

        // 2. Translation Service: Immutable original content & translation quality
        $translationService = app(TranslationService::class);

        $source = NewsSource::create([
            'name' => 'Prothom Alo Business',
            'slug' => 'prothom-alo-biz',
            'source_type' => 'rss',
            'language' => 'bn',
            'is_active' => true,
        ]);

        $originalTitle = 'বাংলাদেশ ব্যাংকের বৈদেশিক মুদ্রার রিজার্ভ বৃদ্ধি পেয়েছে';
        $originalContent = 'চলতি মাসে রেমিট্যান্স প্রবাহ বৃদ্ধির কারণে বাংলাদেশ ব্যাংকের রিজার্ভ ২০ বিলিয়ন ডলার ছাড়িয়েছে।';
        $bnArticle = Article::create([
            'source_id' => $source->id,
            'title' => $originalTitle,
            'canonical_url' => 'https://prothomalo.test/business/reserve-growth-2026',
            'content_raw' => $originalContent,
            'original_language' => 'bn',
            'detected_language' => 'bn',
            'status' => 'published',
        ]);

        // Translate to English
        $translation = $translationService->translateArticle($bnArticle, 'en');

        // Verify original content remained completely immutable
        $refreshedArticle = $bnArticle->fresh();
        $this->assertEquals($originalTitle, $refreshedArticle->title);
        $this->assertEquals($originalContent, $refreshedArticle->content_raw);
        $this->assertEquals('bn', $refreshedArticle->original_language);

        // Verify translation record metadata
        $this->assertEquals($bnArticle->id, $translation->article_id);
        $this->assertEquals('bn', $translation->original_language);
        $this->assertEquals('en', $translation->target_language);
        $this->assertNotEmpty($translation->translated_title);
        $this->assertStringContainsString('Bangladesh', $translation->translated_title);
        $this->assertEquals('completed', $translation->status);

        // 3. Translation Quality Evaluation (Entities & Numbers preservation)
        $qualityDataset = [
            [
                'source_text' => 'বাংলাদেশ ব্যাংকের রিজার্ভ বৃদ্ধি পেয়ে ২০ বিলিয়ন ডলার হয়েছে।',
                'source_lang' => 'bn',
                'target_lang' => 'en',
                'expected_entities' => ['Bangladesh', 'Bank'],
                'expected_numbers' => ['২০'],
            ]
        ];
        $qualityResult = $translationService->evaluateTranslations($qualityDataset);
        $this->assertEquals(1, $qualityResult['total_samples']);
        $this->assertGreaterThanOrEqual(0.80, $qualityResult['overall_quality_score']);

        // 4. Multilingual Unified Search: Search in English for translated Bengali article
        $searchService = app(UnifiedSearchService::class);
        $queryDto = new SearchQueryDTO(query: 'Bangladesh');
        $searchResponse = $searchService->search($queryDto, $this->adminUser);

        $this->assertTrue($searchResponse['success']);
        $this->assertNotEmpty($searchResponse['results']);
        $matched = collect($searchResponse['results'])->firstWhere('id', $bnArticle->id);
        $this->assertNotNull($matched);
        $this->assertEquals('bn', $matched['original_language']);
    }

    /**
     * GROUP D: Source Reliability Calibration & Diversity Safeguards.
     */
    public function test_group_d_source_reliability_sample_size_guardrails_and_syndication_diversity(): void
    {
        $reliabilityService = app(SourceReliabilityService::class);

        // 1. New source with < 5 articles: Must receive insufficient_evidence status
        $newSource = NewsSource::create([
            'name' => 'Emerging Capital Chronicle',
            'slug' => 'emerging-cap-chronicle',
            'source_type' => 'rss',
            'is_active' => true,
        ]);

        $provisionalProfile = $reliabilityService->calculateReliabilityProfile($newSource);
        $this->assertEquals('insufficient_evidence', $provisionalProfile['status']);
        $this->assertFalse($provisionalProfile['has_sufficient_evidence']);
        $this->assertEquals('low', $provisionalProfile['confidence_level']);
        $this->assertEquals(0, $provisionalProfile['sample_size']);
        $this->assertStringContainsString('Insufficient Evidence', $provisionalProfile['display_tier']);
        $this->assertStringContainsString('High uncertainty', $provisionalProfile['uncertainty_indicator']);

        // 2. Add 5 articles to meet calibration threshold
        for ($i = 1; $i <= 5; $i++) {
            Article::create([
                'source_id' => $newSource->id,
                'title' => "Capital Chronicle Dispatch Article #{$i}",
                'canonical_url' => "https://chronicle.test/dispatch/{$i}",
                'status' => 'published',
            ]);
        }

        $calibratedProfile = $reliabilityService->calculateReliabilityProfile($newSource);
        $this->assertEquals('established', $calibratedProfile['status']);
        $this->assertTrue($calibratedProfile['has_sufficient_evidence']);
        $this->assertEquals(5, $calibratedProfile['sample_size']);
        $this->assertEquals('Tier 2', $calibratedProfile['tier']);
        $this->assertStringContainsString('Calibrated evidence profile', $calibratedProfile['uncertainty_indicator']);

        // 3. Source Diversity & Syndication Detection: Wire reprints do NOT inflate corroboration
        $diversityService = app(SourceDiversityService::class);

        $wireArticle1 = Article::create([
            'source_id' => $newSource->id,
            'title' => 'OPEC Agrees on Modest Oil Production Increase for Q1',
            'content_raw' => 'Vienna (Reuters) - OPEC and its allies reached an agreement today to slightly raise output.',
            'canonical_url' => 'https://chronicle.test/opec-wire-reuters',
            'status' => 'published',
        ]);

        $syndicationResult = $diversityService->detectSyndication($wireArticle1);
        $this->assertTrue($syndicationResult['is_syndicated']);
        $this->assertEquals('Reuters', $syndicationResult['wire_network']);

        // Second publisher reprints the same Reuters dispatch
        $publisherB = NewsSource::create([
            'name' => 'Middle East Energy Times',
            'slug' => 'me-energy-times',
            'source_type' => 'rss',
            'canonical_domain' => 'energytimes.test',
            'is_active' => true,
        ]);

        $wireArticle2 = Article::create([
            'source_id' => $publisherB->id,
            'title' => 'OPEC Agrees on Modest Oil Production Increase for Q1',
            'content_raw' => 'According to Reuters reports, OPEC members finalized a consensus on output targets.',
            'canonical_url' => 'https://energytimes.test/opec-reuters-reprint',
            'status' => 'published',
        ]);

        $metrics = $diversityService->calculateIndependenceMetrics([$wireArticle1, $wireArticle2]);
        $this->assertEquals(2, $metrics['raw_article_count']);
        $this->assertEquals(2, $metrics['publisher_count']);
        // Both articles trace back to wire:Reuters, so independent reporting entities count is 1
        $this->assertEquals(1, $metrics['independent_reporting_count']);
        $this->assertEquals(2, $metrics['syndicated_count']);
        $this->assertContains('Reuters', $metrics['wire_networks']);
    }

    /**
     * GROUP E: Geographic Coverage Freshness Windows & Gap Root Causes.
     */
    public function test_group_e_geographic_coverage_freshness_windows_and_gap_root_causes(): void
    {
        $coverageService = app(GeographicCoverageService::class);
        $gapService = app(CoverageGapDetectionService::class);

        // Create an active source in BD with recent ingestion
        $bdSource = NewsSource::create([
            'name' => 'The Daily Star BD',
            'slug' => 'daily-star-bd-cov',
            'source_type' => 'rss',
            'country_code' => 'BD',
            'continent' => 'Asia',
            'is_active' => true,
            'health_status' => 'healthy',
            'last_successful_fetch_at' => now()->subHours(2),
        ]);

        // Create an article with distinct geographic event scope
        Article::create([
            'source_id' => $bdSource->id,
            'title' => 'Singapore and Bangladesh Finalize Bilateral Trade Accord',
            'canonical_url' => 'https://dailystar.test/trade-accord-sg-bd',
            'geographic_scope' => 'SG', // Event location is Singapore, while publisher is Bangladesh
            'published_at' => now()->subHours(1),
            'status' => 'published',
        ]);

        // Test coverage breakdown across windows
        $breakdown24h = $coverageService->getCoverageBreakdown('24h');
        $this->assertEquals('24h', $breakdown24h['measurement_window']);
        $this->assertGreaterThanOrEqual(1, $breakdown24h['metrics']['sources_with_recent_ingestion']);
        // Verify separation of publisher country from event country
        $this->assertGreaterThanOrEqual(1, $breakdown24h['metrics']['countries_represented_by_sources']);
        $this->assertGreaterThanOrEqual(1, $breakdown24h['metrics']['countries_represented_by_events']);

        // Test Coverage Gap Detection Root Causes
        $gapsReport = $gapService->detectGaps('24h');
        $this->assertGreaterThanOrEqual(1, $gapsReport['total_gaps']);

        // Verify every gap explains calculation methodology and root cause
        foreach ($gapsReport['gaps'] as $gap) {
            $this->assertNotEmpty($gap['calculation_method']);
            $this->assertContains($gap['root_cause'], ['missing_sources', 'ingestion_failure', 'all_sources_inactive', 'single_source_monopoly']);
            $this->assertNotEmpty($gap['recommended_source_types']);
        }
    }

    /**
     * GROUP F: SSRF Hardening, Credential Masking & Multi-Tenant Boundaries.
     */
    public function test_group_f_ssrf_port_and_alternate_ip_defense_and_tenant_isolation(): void
    {
        $feedCollector = app(FeedCollectorService::class);

        // 1. SSRF: Block non-standard dangerous ports (SSH: 22, MySQL: 3306, Redis: 6379)
        try {
            $feedCollector->fetchSourceFeed(new NewsSource(['url' => 'http://example.com:22/rss.xml']));
            $this->fail('Expected SSRF exception for non-standard port 22.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('SSRF Protection', $e->getMessage());
        }

        // 2. SSRF: Block Decimal Integer IP representation (http://2130706433 = 127.0.0.1)
        try {
            $feedCollector->fetchSourceFeed(new NewsSource(['url' => 'http://2130706433/feed.xml']));
            $this->fail('Expected SSRF exception for decimal IP representation.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('SSRF Protection', $e->getMessage());
        }

        // 3. SSRF: Block Hex IP representation (http://0x7f000001 = 127.0.0.1)
        try {
            $feedCollector->fetchSourceFeed(new NewsSource(['url' => 'http://0x7f000001/feed.xml']));
            $this->fail('Expected SSRF exception for hex IP representation.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('SSRF Protection', $e->getMessage());
        }

        // 4. SSRF: Block Octal IP representation (http://0177.0.0.1 = 127.0.0.1)
        try {
            $feedCollector->fetchSourceFeed(new NewsSource(['url' => 'http://0177.0.0.1/feed.xml']));
            $this->fail('Expected SSRF exception for octal IP representation.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('SSRF Protection', $e->getMessage());
        }

        // 5. Credential Masking: Plaintext secrets are never exposed in API responses
        $privateSourceA = NewsSource::create([
            'name' => 'Apex Proprietary High-Yield Feed',
            'slug' => 'apex-high-yield-feed',
            'source_type' => 'rest_api',
            'api_endpoint' => 'https://api.apex-intel.test/v1/feed',
            'auth_type' => 'bearer',
            'organization_id' => $this->tenantA->id,
            'is_active' => true,
        ]);
        $privateSourceA->setEncryptedCredentialsAttribute(['token' => 'top_secret_apex_token_99182']);
        $privateSourceA->save();

        $responseA = $this->actingAs($this->tenantUserA)
            ->getJson("/api/v1/admin/sources?organization_id={$this->tenantA->id}");

        $responseA->assertStatus(200);
        $sourceData = collect($responseA->json('data'))->firstWhere('id', $privateSourceA->id);
        $this->assertNotNull($sourceData);
        // Verify token is masked and encrypted column is absent
        $this->assertTrue($sourceData['has_credentials']);
        $this->assertArrayNotHasKey('encrypted_credentials', $sourceData);
        $this->assertStringNotContainsString('top_secret_apex_token_99182', json_encode($sourceData));

        // 6. Tenant Isolation: Tenant B cannot access Tenant A's private source -> 403 Forbidden
        $forbiddenResponse = $this->actingAs($this->tenantUserB)
            ->getJson("/api/v1/admin/sources/{$privateSourceA->id}/reliability-profile");
        $forbiddenResponse->assertStatus(403);

        // 7. Search Isolation: Tenant B cannot find Tenant A's private articles in search
        $privateArticleA = Article::create([
            'source_id' => $privateSourceA->id,
            'title' => 'Confidential Apex M&A Intelligence Briefing',
            'canonical_url' => 'https://apex-internal.test/briefing-001',
            'content_raw' => 'Restricted briefing detailing private acquisition targets.',
            'status' => 'published',
        ]);

        $searchService = app(UnifiedSearchService::class);
        $searchB = $searchService->search(new SearchQueryDTO(query: 'Apex M&A'), $this->tenantUserB);

        $foundItemB = collect($searchB['results'])->firstWhere('id', $privateArticleA->id);
        $this->assertNull($foundItemB, 'Tenant B search incorrectly returned Tenant A private article!');

        // Tenant A searching for their own private article CAN find it
        $searchA = $searchService->search(new SearchQueryDTO(query: 'Apex M&A'), $this->tenantUserA);
        $foundItemA = collect($searchA['results'])->firstWhere('id', $privateArticleA->id);
        $this->assertNotNull($foundItemA, 'Tenant A failed to retrieve their own private article in search!');
    }
}
