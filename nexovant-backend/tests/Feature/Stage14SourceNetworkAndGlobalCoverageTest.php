<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\NewsSource;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\SourceHealthLog;
use App\Models\User;
use App\Services\Discovery\DTO\SearchQueryDTO;
use App\Services\Discovery\UnifiedSearchService;
use App\Services\Ingestion\Connectors\AcademicRepositoryConnector;
use App\Services\Ingestion\Connectors\GovernmentPublicDisclosureConnector;
use App\Services\Ingestion\Connectors\RestApiSourceConnector;
use App\Services\Ingestion\Connectors\RssAtomConnector;
use App\Services\Ingestion\Connectors\SourceConnectorManager;
use App\Services\Multilingual\LanguageDetectionService;
use App\Services\Multilingual\TranslationService;
use App\Services\SourceNetwork\CoverageGapDetectionService;
use App\Services\SourceNetwork\GeographicCoverageService;
use App\Services\SourceNetwork\SourceDiversityService;
use App\Services\SourceNetwork\SourceHealthService;
use App\Services\SourceNetwork\SourceOnboardingService;
use App\Services\SourceNetwork\SourceReliabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Stage14SourceNetworkAndGlobalCoverageTest extends TestCase
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
            'email' => 'admin.sources@nexovant.test',
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
            'email' => 'analyst.a@apexfi.test',
        ]);

        $this->tenantUserB = User::factory()->create([
            'account_type' => 'admin',
            'email' => 'analyst.b@boreal.test',
        ]);

        OrganizationMember::create([
            'organization_id' => $this->tenantA->id,
            'user_id' => $this->tenantUserA->id,
            'role' => 'admin',
        ]);

        OrganizationMember::create([
            'organization_id' => $this->tenantB->id,
            'user_id' => $this->tenantUserB->id,
            'role' => 'admin',
        ]);
    }

    /**
     * 1. Centralized Source Registry: Multi-type sources, country, continent, languages, rate limits.
     */
    public function test_source_registry_supports_multi_type_sources_with_metadata(): void
    {
        $rssSource = NewsSource::create([
            'name' => 'The Daily Star Bangladesh',
            'slug' => 'the-daily-star-bd',
            'publisher_name' => 'Mediaworld Ltd',
            'source_type' => 'rss',
            'website_url' => 'https://www.thedailystar.net',
            'feed_url' => 'https://www.thedailystar.net/rss.xml',
            'canonical_domain' => 'thedailystar.net',
            'country_code' => 'BD',
            'continent' => 'Asia',
            'language' => 'en',
            'supported_languages' => ['en', 'bn'],
            'publication_frequency' => 'hourly',
            'credibility_score' => 0.90,
            'rate_limit_per_minute' => 60,
            'is_active' => true,
            'editorial_status' => 'approved',
            'health_status' => 'healthy',
        ]);

        $govSource = NewsSource::create([
            'name' => 'Federal Reserve Monetary Policy Releases',
            'slug' => 'fed-monetary-policy',
            'publisher_name' => 'Board of Governors of the Federal Reserve System',
            'source_type' => 'regulatory',
            'website_url' => 'https://www.federalreserve.gov',
            'api_endpoint' => 'https://www.federalreserve.gov/feeds/press_monetary.xml',
            'canonical_domain' => 'federalreserve.gov',
            'country_code' => 'US',
            'continent' => 'North America',
            'language' => 'en',
            'publication_frequency' => 'realtime',
            'credibility_score' => 0.99,
            'rate_limit_per_minute' => 30,
            'is_active' => true,
            'editorial_status' => 'approved',
            'health_status' => 'healthy',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/sources?country_code=BD');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'The Daily Star Bangladesh')
            ->assertJsonPath('data.0.country_code', 'BD')
            ->assertJsonPath('data.0.continent', 'Asia');
    }

    /**
     * 2. Source Onboarding Workflow & SSRF Protection.
     */
    public function test_source_onboarding_workflow_transitions_and_ssrf_protection(): void
    {
        // Test SSRF Rejection: Attempt to onboard internal RFC1918 / loopback address
        $ssrfPayload = [
            'name' => 'Internal Cloud Metadata Exploit',
            'source_type' => 'rss',
            'feed_url' => 'http://169.254.169.254/latest/meta-data',
            'country_code' => 'US',
        ];

        $failResponse = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/sources/onboard', $ssrfPayload);

        $failResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'ONBOARDING_FAILED');

        // Test Legitimate Source Submission
        Http::fake([
            'https://api.worldbank.org/*' => Http::response([
                'page' => 1,
                'data' => [
                    [
                        'id' => 'wb-001',
                        'title' => 'Global Economic Prospects 2026',
                        'url' => 'https://api.worldbank.org/reports/wb-001',
                        'summary' => 'Inflation trends stabilizing across South Asia and Europe.',
                        'published_at' => now()->toIso8601String(),
                    ]
                ]
            ], 200),
        ]);

        $validPayload = [
            'name' => 'World Bank Development API',
            'publisher_name' => 'World Bank Group',
            'source_type' => 'rest_api',
            'api_endpoint' => 'https://api.worldbank.org/reports',
            'country_code' => 'US',
            'continent' => 'North America',
            'language' => 'en',
            'auth_type' => 'none',
            'attribution_requirements' => 'Source: World Bank Development Indicators',
            'terms_of_use_url' => 'https://worldbank.org/terms',
            'rate_limit_per_minute' => 60,
        ];

        $onboardResponse = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/sources/onboard', $validPayload);

        $onboardResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.editorial_status', 'pending_review');

        $sourceId = $onboardResponse->json('data.id');

        // Verify it appears in onboarding queue
        $queueResponse = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/sources/onboarding-queue');

        $queueResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'World Bank Development API');

        // Approve Source
        $approveResponse = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/admin/sources/{$sourceId}/approve", [
                'notes' => 'Endpoint and attribution compliance verified.',
            ]);

        $approveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.editorial_status', 'approved')
            ->assertJsonPath('data.is_active', true);

        // Suspend Source
        $suspendResponse = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/admin/sources/{$sourceId}/suspend", [
                'reason' => 'Scheduled maintenance on external API.',
            ]);

        $suspendResponse->assertStatus(200)
            ->assertJsonPath('data.editorial_status', 'temporarily_suspended')
            ->assertJsonPath('data.is_active', false);
    }

    /**
     * 3. Ingestion Connectors (RSS/Atom, REST, Gov Disclosure, Academic Repository).
     */
    public function test_source_connectors_execution_and_probe(): void
    {
        $govSource = NewsSource::create([
            'name' => 'Bangladesh Bank Regulatory Circulars',
            'slug' => 'bb-circulars',
            'publisher_name' => 'Bangladesh Bank',
            'source_type' => 'regulatory',
            'website_url' => 'https://www.bb.org.bd',
            'api_endpoint' => 'https://www.bb.org.bd/api/circulars',
            'canonical_domain' => 'bb.org.bd',
            'country_code' => 'BD',
            'continent' => 'Asia',
            'language' => 'bn',
            'is_active' => true,
            'editorial_status' => 'approved',
            'attribution_requirements' => 'Official Circular: Bangladesh Bank',
        ]);

        $academicSource = NewsSource::create([
            'name' => 'arXiv Economics & Quantitative Finance',
            'slug' => 'arxiv-qfin',
            'publisher_name' => 'Cornell University arXiv',
            'source_type' => 'academic',
            'website_url' => 'https://arxiv.org',
            'feed_url' => 'https://export.arxiv.org/rss/q-fin',
            'canonical_domain' => 'arxiv.org',
            'country_code' => 'US',
            'continent' => 'North America',
            'language' => 'en',
            'is_active' => true,
            'editorial_status' => 'approved',
            'attribution_requirements' => 'arXiv Preprint Archive (q-fin)',
        ]);

        // Mock external responses for connector test
        Http::fake([
            'https://www.bb.org.bd/api/circulars*' => Http::response([
                'items' => [
                    [
                        'title' => 'BRPD Circular No 04: Foreign Exchange Exposure Limits',
                        'link' => 'https://www.bb.org.bd/mediaroom/circulars/brpd04.pdf',
                        'published_date' => now()->toIso8601String(),
                        'summary' => 'Directives regarding open net foreign exchange positions for scheduled banks.',
                    ]
                ]
            ], 200),
            'https://export.arxiv.org/rss/q-fin*' => Http::response(
                '<?xml version="1.0" encoding="UTF-8"?>
                <rss version="2.0">
                  <channel>
                    <title>arXiv: q-fin updates</title>
                    <link>http://export.arxiv.org/rss/q-fin</link>
                    <description>Quantitative Finance preprints</description>
                    <item>
                      <title>Machine Learning in Macroeconomic Nowcasting</title>
                      <link>http://arxiv.org/abs/2601.12345</link>
                      <description>Empirical study on nowcasting inflation utilizing high-frequency financial indicators.</description>
                    </item>
                  </channel>
                </rss>',
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);

        $connectorManager = app(SourceConnectorManager::class);

        // Test Gov Connector
        $govProbe = $connectorManager->testSource($govSource);
        $this->assertTrue($govProbe['success']);
        $this->assertEquals('healthy', $govProbe['status']);
        $this->assertCount(1, $govProbe['sample_items']);
        $this->assertStringContainsString('BRPD Circular', $govProbe['sample_items'][0]['title']);

        // Test Academic Connector
        $academicProbe = $connectorManager->testSource($academicSource);
        $this->assertTrue($academicProbe['success']);
        $this->assertEquals('healthy', $academicProbe['status']);
        $this->assertCount(1, $academicProbe['sample_items']);
        $this->assertStringContainsString('Macroeconomic Nowcasting', $academicProbe['sample_items'][0]['title']);
    }

    /**
     * 4. Source Health Monitoring & Diagnostic Incidents.
     */
    public function test_health_monitoring_and_incident_logging(): void
    {
        $source = NewsSource::create([
            'name' => 'Paris Financial Gazette',
            'slug' => 'paris-fin-gazette',
            'publisher_name' => 'Les Echos Media',
            'source_type' => 'rss',
            'website_url' => 'https://www.paris-gazette.fr',
            'feed_url' => 'https://www.paris-gazette.fr/feed.xml',
            'canonical_domain' => 'paris-gazette.fr',
            'country_code' => 'FR',
            'continent' => 'Europe',
            'language' => 'fr',
            'is_active' => true,
            'editorial_status' => 'approved',
            'health_status' => 'healthy',
        ]);

        Http::fake([
            'https://www.paris-gazette.fr/feed.xml*' => Http::response('Server Internal Error', 500),
        ]);

        $healthService = app(SourceHealthService::class);
        $log = $healthService->checkSourceHealth($source);

        $this->assertEquals('unavailable', $log->status);
        $this->assertEquals(500, $log->http_status);
        $this->assertDatabaseHas('source_health_logs', [
            'source_id' => $source->id,
            'status' => 'unavailable',
            'http_status' => 500,
        ]);

        $overviewResponse = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/sources/health-overview');

        $overviewResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status_breakdown.unavailable', 1);
    }

    /**
     * 5. Evidence-based Source Reliability & Quality Evaluation.
     */
    public function test_evidence_based_reliability_scoring_and_corrections(): void
    {
        $govSource = NewsSource::create([
            'name' => 'European Central Bank Press Releases',
            'slug' => 'ecb-press',
            'publisher_name' => 'European Central Bank',
            'source_type' => 'regulatory',
            'website_url' => 'https://www.ecb.europa.eu',
            'feed_url' => 'https://www.ecb.europa.eu/rss/press.html',
            'canonical_domain' => 'ecb.europa.eu',
            'country_code' => 'DE',
            'continent' => 'Europe',
            'language' => 'en',
            'is_active' => true,
            'editorial_status' => 'approved',
            'terms_of_use_url' => 'https://www.ecb.europa.eu/terms',
            'attribution_requirements' => 'Source: European Central Bank',
        ]);

        $reliabilityService = app(SourceReliabilityService::class);
        $profile = $reliabilityService->calculateReliabilityProfile($govSource);

        $this->assertEquals('Tier 1', $profile['tier']);
        $this->assertGreaterThanOrEqual(90, $profile['overall_score']);
        $this->assertGreaterThanOrEqual(0.90, $profile['primary_source_ratio']);

        // Record a correction
        $updated = $reliabilityService->recordCorrection($govSource, 'Clarified date regarding interest rate announcement.');
        $updatedProfile = $updated->reliability_profile;

        $this->assertEquals(1, $updatedProfile['corrections_count']);
        $this->assertCount(1, $updatedProfile['corrections_history']);
    }

    /**
     * 6. Source Diversity & Independence (syndication detection, no corroboration inflation).
     */
    public function test_source_diversity_detects_syndication_and_prevents_corroboration_inflation(): void
    {
        $sourceA = NewsSource::create([
            'name' => 'Financial Post A',
            'slug' => 'fin-post-a',
            'canonical_domain' => 'finposta.com',
            'is_active' => true,
        ]);

        $sourceB = NewsSource::create([
            'name' => 'Business Observer B',
            'slug' => 'biz-obs-b',
            'canonical_domain' => 'bizobserverb.com',
            'is_active' => true,
        ]);

        // Articles with wire attribution to Reuters
        $art1 = Article::create([
            'source_id' => $sourceA->id,
            'title' => 'Global Central Banks Hold Rates Steady Amid Inflation Easing',
            'slug' => 'rates-steady-a',
            'author' => 'By Reuters Staff',
            'content_raw' => 'GENEVA (Reuters) - Central banks across major economies held borrowing costs unchanged on Wednesday...',
            'published_at' => now(),
            'verification_status' => 'verified',
        ]);

        $art2 = Article::create([
            'source_id' => $sourceB->id,
            'title' => 'Global Central Banks Hold Rates Steady Amid Inflation Easing',
            'slug' => 'rates-steady-b',
            'author' => 'Reuters Wire Service',
            'content_raw' => 'GENEVA (Reuters) - Central banks across major economies held borrowing costs unchanged on Wednesday...',
            'published_at' => now(),
            'verification_status' => 'verified',
        ]);

        $diversityService = app(SourceDiversityService::class);

        // Detect syndication on individual article
        $syndication1 = $diversityService->detectSyndication($art1);
        $this->assertTrue($syndication1['is_syndicated']);
        $this->assertEquals('Reuters', $syndication1['wire_network']);

        // Calculate independence metrics across both articles
        $metrics = $diversityService->calculateIndependenceMetrics([$art1, $art2]);

        $this->assertEquals(2, $metrics['raw_article_count']);
        $this->assertEquals(2, $metrics['syndicated_count']);
        $this->assertContains('Reuters', $metrics['wire_networks']);
    }

    /**
     * 7. Multilingual Ingestion & Immutable Translation Storage.
     */
    public function test_multilingual_language_detection_and_immutable_translation(): void
    {
        $detector = app(LanguageDetectionService::class);

        // Bengali Text
        $bnText = "বাংলাদেশ ব্যাংকের নতুন মুদ্রানীতি ঘোষণা: মূল্যস্ফীতি নিয়ন্ত্রণে সুদের হার অপরিবর্তিত রাখা হয়েছে।";
        $bnResult = $detector->detect($bnText);
        $this->assertEquals('bn', $bnResult['language']);
        $this->assertTrue($bnResult['is_reliable']);

        // French Text
        $frText = "La Banque Centrale Européenne a maintenu ses taux d'intérêt directeurs inchangés pour préserver la stabilité économique.";
        $frResult = $detector->detect($frText);
        $this->assertEquals('fr', $frResult['language']);

        // Create Article in Bengali
        $source = NewsSource::create([
            'name' => 'Prothom Alo Business',
            'slug' => 'prothom-alo-biz',
            'language' => 'bn',
            'country_code' => 'BD',
            'is_active' => true,
        ]);

        $article = Article::create([
            'source_id' => $source->id,
            'title' => 'বাংলাদেশ ব্যাংক রিজার্ভ বৃদ্ধি',
            'slug' => 'bb-reserve-growth-bn',
            'summary' => 'কেন্দ্রীয় ব্যাংকের বৈদেশিক মুদ্রার রিজার্ভে ইতিবাচক ধারা ফিরে এসেছে।',
            'content_raw' => 'বাংলাদেশ ব্যাংকের সর্বশেষ তথ্যানুযায়ী বৈদেশিক মুদ্রার রিজার্ভ বৃদ্ধি পেয়ে ২৫ বিলিয়ন ডলারে পৌঁছেছে।',
            'original_language' => 'bn',
            'detected_language' => 'bn',
            'published_at' => now(),
            'verification_status' => 'verified',
        ]);

        $translationService = app(TranslationService::class);
        $translation = $translationService->translateArticle($article, 'en');

        // Verify translation record created
        $this->assertInstanceOf(ArticleTranslation::class, $translation);
        $this->assertEquals('en', $translation->target_language);
        $this->assertStringContainsString('Bangladesh Bank', $translation->translated_title);
        $this->assertDatabaseHas('article_translations', [
            'article_id' => $article->id,
            'target_language' => 'en',
        ]);

        // VERIFY IMMUTABILITY: Original article must remain untouched
        $freshArticle = $article->fresh();
        $this->assertEquals('বাংলাদেশ ব্যাংক রিজার্ভ বৃদ্ধি', $freshArticle->title);
        $this->assertEquals('bn', $freshArticle->original_language);
    }

    /**
     * 8. Cross-Language Intelligence Discovery in Unified Search.
     */
    public function test_cross_language_unified_search_and_language_filtering(): void
    {
        $source = NewsSource::create([
            'name' => 'Dhaka Financial Times',
            'slug' => 'dhaka-ft',
            'language' => 'bn',
            'country_code' => 'BD',
            'is_active' => true,
        ]);

        $article = Article::create([
            'source_id' => $source->id,
            'title' => 'বাংলাদেশ ব্যাংক মুদ্রাস্ফীতি নিয়ন্ত্রণ কৌশল',
            'slug' => 'bb-inflation-control-strategy',
            'summary' => 'বাংলাদেশ ব্যাংক মুদ্রাস্ফীতি নিয়ন্ত্রণে নতুন পদক্ষেপ নিয়েছে।',
            'content_raw' => 'মূল্যস্ফীতি নিয়ন্ত্রণে কেন্দ্রীয় ব্যাংক কঠোর মুদ্রানীতি অব্যাহত রাখবে।',
            'original_language' => 'bn',
            'detected_language' => 'bn',
            'published_at' => now(),
            'verification_status' => 'verified',
        ]);

        // Create English translation
        ArticleTranslation::create([
            'article_id' => $article->id,
            'original_language' => 'bn',
            'detected_language' => 'bn',
            'target_language' => 'en',
            'translated_title' => 'Bangladesh Bank Inflation Control Strategy',
            'translated_summary' => 'Bangladesh Bank has introduced decisive measures to curb inflation.',
            'translated_content' => 'The central bank will maintain its restrictive monetary posture to rein in core price pressures.',
            'translation_provider' => 'test_provider',
            'translation_model' => 'test_model',
            'quality_confidence_score' => 0.95,
            'status' => 'completed',
            'translated_at' => now(),
        ]);

        $searchService = app(UnifiedSearchService::class);

        // Search in English for "Inflation Control" -> matches translated title!
        $queryDto = new SearchQueryDTO(
            query: 'Inflation Control',
            contentType: 'article',
            isHybrid: false
        );

        $results = $searchService->search($queryDto, $this->adminUser);
        $this->assertNotEmpty($results['results']);
        $matched = collect($results['results'])->firstWhere('id', (string) $article->id);
        $this->assertNotNull($matched);
        $this->assertEquals('bn', $matched['original_language']);

        // Search with language filter: language = 'bn'
        $bnFilterDto = new SearchQueryDTO(
            query: 'বাংলাদেশ',
            contentType: 'article',
            filters: ['language' => 'bn'],
            isHybrid: false
        );

        $bnResults = $searchService->search($bnFilterDto, $this->adminUser);
        $this->assertNotEmpty($bnResults['results']);
    }

    /**
     * 9. Geographic Coverage & Coverage Gap Detection.
     */
    public function test_geographic_coverage_and_coverage_gap_detection(): void
    {
        NewsSource::create([
            'name' => 'Tokyo Stock Exchange Portal',
            'slug' => 'tse-portal',
            'canonical_domain' => 'jpx.co.jp',
            'country_code' => 'JP',
            'continent' => 'Asia',
            'language' => 'en',
            'is_active' => true,
        ]);

        $coverageService = app(GeographicCoverageService::class);
        $breakdown = $coverageService->getCoverageBreakdown();

        $this->assertGreaterThanOrEqual(1, $breakdown['total_countries_covered']);
        $this->assertNotEmpty($breakdown['continents']);

        $gapService = app(CoverageGapDetectionService::class);
        $gaps = $gapService->detectGaps();

        $this->assertGreaterThan(0, $gaps['total_gaps']);
        $this->assertNotEmpty($gaps['gaps']);
        // Verify gap format
        $firstGap = $gaps['gaps'][0];
        $this->assertArrayHasKey('gap_type', $firstGap);
        $this->assertArrayHasKey('severity', $firstGap);
        $this->assertArrayHasKey('recommended_action', $firstGap);
    }

    /**
     * 10. Credentials Encryption At Rest & Response Masking.
     */
    public function test_credential_encryption_at_rest_and_response_masking(): void
    {
        $credentials = [
            'api_key' => 'top_secret_prod_key_998877',
            'bearer_token' => 'jwt_live_token_super_secret',
        ];

        $source = NewsSource::create([
            'name' => 'Private Financial Data Feed',
            'slug' => 'private-fin-data',
            'source_type' => 'rest_api',
            'api_endpoint' => 'https://api.private-data.com/v1',
            'auth_type' => 'bearer',
            'is_active' => true,
        ]);

        $source->setEncryptedCredentialsAttribute($credentials);
        $source->save();

        // 1. Verify encrypted in database (raw attribute is encrypted string)
        $rawEncrypted = $source->getRawOriginal('encrypted_credentials');
        $this->assertNotNull($rawEncrypted);
        $this->assertStringNotContainsString('top_secret_prod_key_998877', $rawEncrypted);

        // 2. Verify decryption helper works internally
        $decrypted = $source->getDecryptedCredentials();
        $this->assertEquals('top_secret_prod_key_998877', $decrypted['api_key']);

        // 3. Verify API endpoint masks credentials completely
        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/admin/sources?search=Private");

        $response->assertStatus(200);
        $item = $response->json('data.0');
        $this->assertArrayNotHasKey('encrypted_credentials', $item);
        $this->assertTrue($item['has_credentials']);
    }

    /**
     * 11. Multi-Tenant Isolation for Private Organization Sources.
     */
    public function test_multi_tenant_isolation_for_private_organization_sources(): void
    {
        // Private source belonging exclusively to Tenant A
        $privateSourceA = NewsSource::create([
            'name' => 'Apex Proprietary Signal Wire',
            'slug' => 'apex-signal-wire',
            'source_type' => 'rest_api',
            'api_endpoint' => 'https://signals.apexfi.test/feed',
            'organization_id' => $this->tenantA->id,
            'is_active' => true,
            'editorial_status' => 'active',
        ]);

        // Tenant B user attempts to access Tenant A's private source -> 403 Forbidden
        $forbiddenResponse = $this->actingAs($this->tenantUserB)
            ->getJson("/api/v1/admin/sources/{$privateSourceA->id}/reliability-profile");

        $forbiddenResponse->assertStatus(403);

        // Tenant A user CAN access their own private source
        $allowedResponse = $this->actingAs($this->tenantUserA)
            ->getJson("/api/v1/admin/sources/{$privateSourceA->id}/reliability-profile");

        $allowedResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
