<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ClaimEvidence;
use App\Models\EntityRelationship;
use App\Models\ExtractedFact;
use App\Models\IntelligenceEntity;
use App\Models\IntelligenceEvent;
use App\Models\NewsSource;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Publication;
use App\Models\ResearchBrief;
use App\Models\ResearchReport;
use App\Models\SearchAnalyticsLog;
use App\Models\SearchRelevanceFeedback;
use App\Models\StoryCluster;
use App\Models\SubscriberCollection;
use App\Models\SubscriberSavedSearch;
use App\Models\User;
use App\Services\Discovery\SavedSearchService;
use App\Services\Discovery\SourceCorroborationService;
use App\Services\Discovery\UnifiedSearchService;
use App\Services\Discovery\DTO\SearchQueryDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Stage12AdvancedDiscoverySearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $subscriber;
    protected User $tenantUserA;
    protected User $tenantUserB;
    protected Organization $orgA;
    protected Organization $orgB;
    protected NewsSource $sourceReuters;
    protected NewsSource $sourceWire;
    protected Article $articleSemiconductor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriber = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Alexander Vance',
            'email' => 'subscriber.discovery@nexovant.test',
            'password' => bcrypt('Password123!'),
            'account_type' => 'subscriber',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->orgA = Organization::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'owner_id' => $this->subscriber->id,
            'name' => 'Acme Quantum Corp',
            'slug' => 'acme-quantum',
            'is_active' => true,
        ]);

        $this->orgB = Organization::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'owner_id' => $this->subscriber->id,
            'name' => 'Zenith Aerospace',
            'slug' => 'zenith-aerospace',
            'is_active' => true,
        ]);

        $this->tenantUserA = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'User Acme A',
            'email' => 'user.a@acme.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->orgA->id,
            'account_type' => 'subscriber',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        OrganizationMember::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->tenantUserA->id,
            'role' => 'member',
        ]);

        $this->tenantUserB = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'User Zenith B',
            'email' => 'user.b@zenith.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->orgB->id,
            'account_type' => 'subscriber',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        OrganizationMember::create([
            'organization_id' => $this->orgB->id,
            'user_id' => $this->tenantUserB->id,
            'role' => 'member',
        ]);

        $this->sourceReuters = NewsSource::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Global Technology Wire',
            'slug' => 'global-technology-wire',
            'domain' => 'globaltech.test',
            'source_type' => 'rss',
            'url' => 'https://globaltech.test',
            'trust_tier' => 'established_primary',
            'credibility_score' => 0.95,
            'is_active' => true,
        ]);

        $this->sourceWire = NewsSource::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Syndicated Press Outlet',
            'slug' => 'syndicated-press-outlet',
            'domain' => 'syndicatedwire.test',
            'source_type' => 'rss',
            'url' => 'https://syndicatedwire.test',
            'trust_tier' => 'reputable_secondary',
            'credibility_score' => 0.75,
            'is_active' => true,
        ]);

        $this->articleSemiconductor = Article::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Breakthrough EUV High-NA Lithography Milestone in Dresden Fab',
            'slug' => 'breakthrough-euv-high-na-lithography-milestone',
            'canonical_url' => 'https://globaltech.test/news/euv-high-na-dresden',
            'summary' => 'European semiconductor pilot line achieves confirmed 0.55 NA resolution milestone.',
            'content_raw' => 'Full pilot line operations in Dresden announced by consortium partners.',
            'content_normalized' => 'Full pilot line operations in Dresden announced by consortium partners.',
            'content_hash' => hash('sha256', 'Full pilot line operations in Dresden announced by consortium partners.'),
            'source_id' => $this->sourceReuters->id,
            'published_at' => now()->subDays(2),
            'verification_status' => 'confirmed',
            'importance_score' => 8.5,
            'is_editorial_approved' => true,
        ]);
    }

    public function test_unified_search_retrieves_multi_content_types_with_hybrid_scoring(): void
    {
        // 1. Create entities and reports
        $entity = IntelligenceEntity::create([
            'canonical_name' => 'ASML Lithography',
            'slug' => 'asml-lithography',
            'entity_type' => 'company',
            'verification_status' => 'verified',
            'description' => 'Manufacturer of High-NA EUV semiconductor systems.',
        ]);

        $project = \App\Models\ResearchProject::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->subscriber->id,
            'title' => 'High-NA EUV Commercialization Roadmap',
            'research_question' => 'How quickly will High-NA EUV deploy?',
            'status' => 'completed',
        ]);

        $report = ResearchReport::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'project_id' => $project->id,
            'title' => 'High-NA EUV Commercialization Roadmap',
            'slug' => 'high-na-euv-roadmap',
            'executive_summary' => 'Deep technical analysis of High-NA EUV lithography throughput.',
            'content_markdown' => 'Comprehensive technical report.',
            'confidence_score' => 0.94,
            'uncertainty_rating' => 'low',
            'is_public' => true,
        ]);

        $response = $this->actingAs($this->subscriber)->getJson('/api/v1/search?q=Lithography&hybrid=true');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'query',
                'total',
                'results',
                'meta' => [
                    'retrieval_method',
                    'latency_ms',
                    'is_hybrid',
                ],
            ]);

        $results = $response->json('results');
        $this->assertNotEmpty($results);

        // Verify result schema conformity
        $first = $results[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('title', $first);
        $this->assertArrayHasKey('content_type', $first);
        $this->assertArrayHasKey('relevance_score', $first);
        $this->assertArrayHasKey('relevance_explanation', $first);
        $this->assertArrayHasKey('verification_status', $first);
    }

    public function test_advanced_search_filters_and_sorting(): void
    {
        // Older article with lower credibility
        $olderArticle = Article::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Historical Lithography Overview',
            'slug' => 'historical-lithography-overview',
            'canonical_url' => 'https://syndicatedwire.test/news/historical-lithography',
            'summary' => 'Legacy DUV lithography systems from 2021.',
            'content_raw' => 'Old legacy systems.',
            'content_normalized' => 'Old legacy systems.',
            'content_hash' => hash('sha256', 'Old legacy systems.'),
            'source_id' => $this->sourceWire->id,
            'published_at' => now()->subYears(2),
            'verification_status' => 'unverified',
            'importance_score' => 3.0,
            'is_editorial_approved' => false,
        ]);

        // Filter: only 7d and min_credibility 80
        $response = $this->actingAs($this->subscriber)->getJson('/api/v1/search?' . http_build_query([
            'q' => 'Lithography',
            'content_type' => 'article',
            'filters' => [
                'date_preset' => '7d',
                'min_credibility' => 80,
                'verification_status' => 'verified',
            ],
            'sort_by' => 'highest_credibility',
        ]));

        $response->assertStatus(200);
        $results = $response->json('results');

        // Only the fresh high-credibility article should be returned
        $this->assertCount(1, $results);
        $this->assertEquals($this->articleSemiconductor->id, $results[0]['id']);
        $this->assertEquals('verified', $results[0]['verification_status']);
    }

    public function test_source_corroboration_distinguishes_independent_from_syndicated_reprints(): void
    {
        $cluster = StoryCluster::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'article_id' => $this->articleSemiconductor->id,
            'title' => 'Consortium Deploys Next-Gen Pilot Line',
            'slug' => 'consortium-deploys-next-gen-pilot-line',
            'summary' => 'Multiple publications report on Dresden installation.',
            'first_seen_at' => now()->subDay(),
            'status' => 'clustered',
        ]);

        $fact = ExtractedFact::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'cluster_id' => $cluster->id,
            'source_id' => $this->sourceReuters->id,
            'claim' => 'Consortium pilot line achieves 0.55 NA resolution in Dresden.',
            'verification_status' => 'verified',
            'confidence_score' => 0.95,
        ]);

        // Independent supporting evidence
        ClaimEvidence::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'fact_id' => $fact->id,
            'source_id' => $this->sourceReuters->id,
            'stance' => 'supports',
            'evidence_text' => 'Consortium engineers confirmed complete optical qualification.',
            'is_syndicated_reprint' => false,
            'reliability_score' => 0.95,
        ]);

        // Syndicated duplicate evidence
        ClaimEvidence::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'fact_id' => $fact->id,
            'source_id' => $this->sourceWire->id,
            'stance' => 'supports',
            'evidence_text' => 'Consortium engineers confirmed complete optical qualification (reprint).',
            'is_syndicated_reprint' => true,
            'reliability_score' => 0.70,
        ]);

        $corroborationService = app(SourceCorroborationService::class);
        $report = $corroborationService->getCorroborationReport($this->articleSemiconductor->id, 'article');

        $this->assertNotEmpty($report['primary_source']);
        $this->assertNotEmpty($report['syndicated_reprints']);
        $this->assertGreaterThanOrEqual(1, $report['syndicated_count']);
        $this->assertEquals('verified', $report['verification_status']);

        // Assert API endpoint returns corroboration
        $apiRes = $this->actingAs($this->subscriber)->getJson("/api/v1/discovery/corroboration/{$this->articleSemiconductor->id}?type=article");
        $apiRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', $this->articleSemiconductor->title);
    }

    public function test_entity_discovery_distinguishes_verified_vs_inferred_relationships(): void
    {
        $entityAsml = IntelligenceEntity::create([
            'canonical_name' => 'ASML Global Holding',
            'slug' => 'asml-global-holding',
            'entity_type' => 'company',
            'verification_status' => 'verified',
            'description' => 'Semiconductor lithography vendor.',
        ]);

        $entityTsmc = IntelligenceEntity::create([
            'canonical_name' => 'TSMC Foundry Services',
            'slug' => 'tsmc-foundry-services',
            'entity_type' => 'company',
            'verification_status' => 'verified',
            'description' => 'Dedicated semiconductor foundry.',
        ]);

        // Verified relationship
        EntityRelationship::create([
            'source_entity_id' => $entityAsml->id,
            'relationship_type' => 'supplies',
            'target_entity_id' => $entityTsmc->id,
            'confidence_score' => 0.98,
            'verification_status' => 'verified',
            'evidence_excerpt' => 'Confirmed procurement contracts for commercial High-NA tool delivery.',
            'is_active' => true,
        ]);

        // AI-Inferred relationship
        EntityRelationship::create([
            'source_entity_id' => $entityAsml->id,
            'relationship_type' => 'co_investor',
            'target_entity_id' => $entityTsmc->id,
            'confidence_score' => 0.72,
            'verification_status' => 'single_source',
            'evidence_excerpt' => 'Speculative industry rumors around joint advanced packaging R&D facility.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->subscriber)->getJson("/api/v1/discovery/entities/{$entityAsml->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.entity.canonical_name', 'ASML Global Holding')
            ->assertJsonPath('data.relationships.verified_count', 1)
            ->assertJsonPath('data.relationships.inferred_count', 1);

        $verified = $response->json('data.relationships.verified');
        $this->assertTrue($verified[0]['is_verified']);
    }

    public function test_saved_search_crud_and_notification_engine_alert_dispatch(): void
    {
        // 1. Create Saved Search via API
        $res = $this->actingAs($this->subscriber)->postJson('/api/v1/workspace/saved-searches', [
            'name' => 'High-NA Lithography Monitor',
            'query' => 'Lithography',
            'description' => 'Track pilot line deployment announcements.',
            'alert_frequency' => 'immediate',
            'delivery_channels' => ['in_app'],
            'is_active' => true,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'High-NA Lithography Monitor');

        $searchId = $res->json('data.id');

        // 2. Preview Matches
        $matchRes = $this->actingAs($this->subscriber)->getJson("/api/v1/workspace/saved-searches/{$searchId}/matches");
        $matchRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 1);

        // 3. Automated Alert Trigger via Ingestion Evaluator
        $savedSearchService = app(SavedSearchService::class);
        $newArticle = Article::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'New Breakthrough Lithography Announcement',
            'slug' => 'new-lithography-announcement',
            'canonical_url' => 'https://globaltech.test/news/new-lithography-announcement',
            'summary' => 'Second phase expansion confirmed.',
            'content_raw' => 'Additional tools ordered for Lithography consortium.',
            'content_normalized' => 'Additional tools ordered for Lithography consortium.',
            'content_hash' => hash('sha256', 'Additional tools ordered for Lithography consortium.'),
            'source_id' => $this->sourceReuters->id,
            'published_at' => now(),
            'verification_status' => 'confirmed',
            'importance_score' => 8.0,
            'is_editorial_approved' => true,
        ]);

        $alertCount = $savedSearchService->evaluateItemAndAlert($newArticle, 'article');
        $this->assertEquals(1, $alertCount);

        // Verify in_app notification was generated for the subscriber
        $notification = Notification::where('user_id', $this->subscriber->id)
            ->where('category', 'monitor_alert')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('Saved Search Match', $notification->title);
    }

    public function test_grounded_research_brief_synthesizes_13_sections_with_citations(): void
    {
        $response = $this->actingAs($this->subscriber)->postJson('/api/v1/workspace/research-briefs/generate', [
            'question' => 'What are the confirmed deployment schedules for High-NA EUV lithography?',
            'save' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.grounded', true);

        $brief = $response->json('brief');
        $this->assertNotEmpty($brief['structured_sections']);
        $sections = $brief['structured_sections'];

        // Verify 13 mandatory sections
        $expectedSections = [
            'executive_summary',
            'research_question',
            'background_and_context',
            'key_findings',
            'relevant_developments',
            'entities_and_organizations',
            'timeline',
            'supporting_evidence',
            'contradictory_evidence',
            'risk_and_opportunity_analysis',
            'knowledge_gaps',
            'conclusion',
            'references_and_citations',
        ];

        foreach ($expectedSections as $expectedKey) {
            $this->assertArrayHasKey($expectedKey, $sections, "Missing section {$expectedKey}");
        }

        // Test export format
        $briefId = $brief['id'];
        $exportRes = $this->actingAs($this->subscriber)->get("/api/v1/workspace/research-briefs/{$briefId}/export?format=markdown");
        $exportRes->assertStatus(200);
        $this->assertStringContainsString('Executive Briefing', $exportRes->getContent());
        $this->assertStringContainsString('## 1. Executive Summary', $exportRes->getContent());
    }

    public function test_strict_tenant_isolation_prevents_cross_tenant_collection_and_document_leakage(): void
    {
        // Organization A creates a private collection
        $collectionA = SubscriberCollection::create([
            'user_id' => $this->tenantUserA->id,
            'organization_id' => $this->orgA->id,
            'name' => 'Acme Classified Lithography Roadmap',
            'description' => 'Proprietary wafer throughput benchmarks.',
            'visibility' => 'organization',
        ]);

        // User A searches and MUST see their organization's collection
        $resA = $this->actingAs($this->tenantUserA)->getJson('/api/v1/search?q=Lithography&content_type=collection');
        $resA->assertStatus(200);
        $resultsA = $resA->json('results');
        $this->assertCount(1, $resultsA);
        $this->assertEquals($collectionA->id, $resultsA[0]['id']);

        // User B from Organization B searches with same query and MUST NOT see Organization A's collection
        $resB = $this->actingAs($this->tenantUserB)->getJson('/api/v1/search?q=Lithography&content_type=collection');
        $resB->assertStatus(200);
        $resultsB = $resB->json('results');
        $this->assertEmpty($resultsB);

        // Anonymous/Public user search MUST NOT see Organization A's collection
        $publicRes = $this->getJson('/api/v1/search?q=Lithography&content_type=collection');
        $publicRes->assertStatus(200);
        $this->assertEmpty($publicRes->json('results'));
    }

    public function test_search_analytics_logging_and_relevance_feedback(): void
    {
        // 1. Submit search query
        $this->actingAs($this->subscriber)->getJson('/api/v1/search?q=Quantum+Computing+Telemetry');

        // Verify telemetry logged
        $log = SearchAnalyticsLog::where('query', 'Quantum Computing Telemetry')->first();
        $this->assertNotNull($log);
        $this->assertGreaterThanOrEqual(0, $log->duration_ms);

        // 2. Submit user relevance feedback
        $feedbackRes = $this->actingAs($this->subscriber)->postJson('/api/v1/search/feedback', [
            'query' => 'Quantum Computing Telemetry',
            'result_id' => $this->articleSemiconductor->id,
            'result_type' => 'article',
            'feedback_rating' => 'relevant',
            'comments' => 'High precision verified information.',
        ]);

        $feedbackRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('search_relevance_feedbacks', [
            'query' => 'Quantum Computing Telemetry',
            'feedback_rating' => 'relevant',
        ]);
    }

    public function test_admin_search_analytics_endpoints_and_unanswered_queries(): void
    {
        $admin = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Platform Administrator',
            'email' => 'admin.discovery@nexovant.si',
            'password' => bcrypt('Password123!'),
            'account_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Create empty search query log
        SearchAnalyticsLog::create([
            'query' => 'Rare Earth Element Deposition',
            'search_category' => 'all',
            'result_count' => 0,
            'is_empty' => true,
            'retrieval_method' => 'hybrid',
            'duration_ms' => 12,
            'created_at' => now(),
        ]);

        // Overview
        $overviewRes = $this->actingAs($admin)->getJson('/api/v1/admin/search-analytics/overview');
        $overviewRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'metrics' => ['total_searches', 'empty_searches', 'empty_rate_pct', 'avg_latency_ms'],
                    'top_queries',
                    'unanswered_queries',
                ],
            ]);

        // Unanswered queries list
        $unansweredRes = $this->actingAs($admin)->getJson('/api/v1/admin/search-analytics/unanswered');
        $unansweredRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.query', 'Rare Earth Element Deposition');
    }

    public function test_research_brief_tenant_authorization_isolation(): void
    {
        // Tenant A user generates a private brief
        $briefA = ResearchBrief::create([
            'user_id' => $this->tenantUserA->id,
            'organization_id' => $this->orgA->id,
            'title' => 'Confidential Acme Quantum Computing Milestones',
            'research_question' => 'What are the proprietary qubit yields?',
            'executive_summary' => 'Proprietary qubit synthesis.',
            'structured_sections' => ['summary' => 'Confidential'],
            'citations' => [],
            'is_shared_with_org' => false, // strictly private to User A
        ]);

        // User A can access
        $this->actingAs($this->tenantUserA)->getJson("/api/v1/workspace/research-briefs/{$briefA->id}")
            ->assertStatus(200);

        // User B from another organization MUST be forbidden (404/not found in authorized scope)
        $this->actingAs($this->tenantUserB)->getJson("/api/v1/workspace/research-briefs/{$briefA->id}")
            ->assertStatus(404);
    }

    public function test_entity_graph_and_timeline_endpoints(): void
    {
        $entity = IntelligenceEntity::create([
            'canonical_name' => 'Imec R&D Consortium',
            'slug' => 'imec-rd-consortium',
            'entity_type' => 'institution',
            'verification_status' => 'verified',
        ]);

        $event = IntelligenceEvent::create([
            'title' => 'High-NA Joint Pilot Line Launch',
            'slug' => 'high-na-pilot-line-launch',
            'event_category' => 'technology_breakthrough',
            'occurred_at' => now()->subMonths(3),
            'reported_at' => now()->subMonths(3),
            'verification_status' => 'confirmed',
            'primary_entity_id' => $entity->id,
        ]);

        // Timeline endpoint
        $timeRes = $this->actingAs($this->subscriber)->getJson("/api/v1/discovery/entities/{$entity->id}/timeline");
        $timeRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.title', 'High-NA Joint Pilot Line Launch');

        // Graph endpoint
        $graphRes = $this->actingAs($this->subscriber)->getJson("/api/v1/discovery/entities/{$entity->id}/graph");
        $graphRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['nodes', 'links']]);
    }

    public function test_saved_search_update_toggle_delete_lifecycle(): void
    {
        $savedSearch = SubscriberSavedSearch::create([
            'user_id' => $this->subscriber->id,
            'name' => 'AI Accelerator Architectures',
            'query' => 'Accelerator',
            'is_active' => true,
        ]);

        // Update
        $this->actingAs($this->subscriber)->putJson("/api/v1/workspace/saved-searches/{$savedSearch->id}", [
            'name' => 'AI Chiplet Architectures',
            'alert_frequency' => 'weekly_digest',
        ])->assertStatus(200)
          ->assertJsonPath('data.name', 'AI Chiplet Architectures')
          ->assertJsonPath('data.alert_frequency', 'weekly_digest');

        // Toggle
        $this->actingAs($this->subscriber)->postJson("/api/v1/workspace/saved-searches/{$savedSearch->id}/toggle")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        // Delete
        $this->actingAs($this->subscriber)->deleteJson("/api/v1/workspace/saved-searches/{$savedSearch->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('subscriber_saved_searches', ['id' => $savedSearch->id]);
    }
}
