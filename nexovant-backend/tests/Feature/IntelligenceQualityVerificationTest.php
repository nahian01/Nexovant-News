<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ClaimEvidence;
use App\Models\DiscoveredStory;
use App\Models\EntityAlias;
use App\Models\EntityRelationship;
use App\Models\EntityResolutionReview;
use App\Models\ExtractedFact;
use App\Models\IntelligenceEntity;
use App\Models\IntelligenceEvent;
use App\Models\IntelligenceTrend;
use App\Models\NewsSource;
use App\Models\ResearchProject;
use App\Models\ResearchReport;
use App\Models\StoryCluster;
use App\Models\User;
use App\Services\Research\AdvancedFactVerificationService;
use App\Services\Research\DeepResearchEngineService;
use App\Services\Research\EntityResolutionService;
use App\Services\Research\HistoricalTimelineService;
use App\Services\Research\RelationshipGraphService;
use App\Services\Research\TrendIntelligenceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntelligenceQualityVerificationTest extends TestCase
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
            'name' => 'Reuters Global Technology',
            'slug' => 'reuters-global-technology',
            'url' => 'https://reuters.example.com',
            'feed_url' => 'https://reuters.example.com/feed.xml',
            'source_type' => 'RSS',
            'is_active' => true,
            'credibility_score' => 95.0,
            'trust_tier' => 'established_primary',
            'source_category' => 'wire',
            'geographic_region' => 'GLOBAL',
        ]);

        $this->source2 = NewsSource::create([
            'name' => 'Bloomberg Technology Dispatch',
            'slug' => 'bloomberg-technology-dispatch',
            'url' => 'https://bloomberg.example.com',
            'feed_url' => 'https://bloomberg.example.com/feed.xml',
            'source_type' => 'RSS',
            'is_active' => true,
            'credibility_score' => 92.0,
            'trust_tier' => 'established_primary',
            'source_category' => 'wire',
            'geographic_region' => 'GLOBAL',
        ]);
    }

    /**
     * Test 1: Entity Resolution, Alias Matching and Deduplication
     */
    public function test_entity_resolution_and_alias_matching(): void
    {
        $service = app(EntityResolutionService::class);

        // 1. Create canonical entity
        $google = $service->createEntity('Alphabet Inc.', 'company', [
            'legal_name' => 'Alphabet Incorporated',
            'country' => 'US',
            'headquarters' => 'Mountain View, CA',
            'founding_year' => 2015,
            'verification_status' => 'verified',
        ]);
        $google->addAlias('Google', 'brand');
        $google->addAlias('GOOGL', 'abbreviation');

        // 2. Resolve by exact canonical name
        $resolved1 = $service->resolveEntity('Alphabet Inc.', 'company');
        $this->assertEquals($google->id, $resolved1->id);

        // 3. Resolve by alias 'Google'
        $resolved2 = $service->resolveEntity('Google', 'company');
        $this->assertEquals($google->id, $resolved2->id);

        // 4. Resolve by alias ticker 'GOOGL'
        $resolved3 = $service->resolveEntity('GOOGL', 'company');
        $this->assertEquals($google->id, $resolved3->id);
    }

    /**
     * Test 2: Low-Confidence Entity Matches are Queued for Review (No False Automatic Merges)
     */
    public function test_entity_fuzzy_matching_and_review_queue(): void
    {
        $service = app(EntityResolutionService::class);

        $nvidia = $service->createEntity('Nvidia Corporation', 'company', [
            'country' => 'US',
            'verification_status' => 'verified',
        ]);

        // Slightly ambiguous variation: 'Nvidia Corp Inc' -> high similarity, matches
        $resolvedHigh = $service->resolveEntity('Nvidia Corp Inc', 'company');
        $this->assertEquals($nvidia->id, $resolvedHigh->id);

        // Moderately similar candidate: 'Nvidian Technologies' (~75% similarity)
        // Must NOT automatically merge into Nvidia Corporation! Must create new entity and queue for review!
        $resolvedModerate = $service->resolveEntity('Nvidian Technologies', 'company');
        $this->assertNotEquals($nvidia->id, $resolvedModerate->id);

        // Verify entry in EntityResolutionReview queue
        $review = EntityResolutionReview::where('entity_id', $resolvedModerate->id)->first();
        $this->assertNotNull($review);
        $this->assertEquals($nvidia->id, $review->potential_match_entity_id);
        $this->assertEquals('pending', $review->status);
    }

    /**
     * Test 3: Entity Merge and Split with Full Audit Trail
     */
    public function test_entity_merge_and_split_with_audit_trail(): void
    {
        $service = app(EntityResolutionService::class);

        $target = $service->createEntity('Microsoft Corporation', 'company', ['verification_status' => 'verified']);
        $source = $service->createEntity('MSFT Cloud Division', 'company', ['verification_status' => 'unverified']);
        $source->addAlias('MSFT Cloud', 'abbreviation');

        // Merge
        $merged = $service->mergeEntities($target, $source, 'Consolidating corporate division into parent enterprise.', $this->admin->id);
        $this->assertEquals($target->id, $merged->id);

        $source->refresh();
        $this->assertEquals('merged', $source->verification_status);
        $this->assertEquals($target->id, $source->merged_into_id);

        // Aliases migrated
        $this->assertTrue(EntityAlias::where('entity_id', $target->id)->where('alias', 'MSFT Cloud')->exists());

        // Split alias into distinct spin-off entity
        $aliasToSplit = EntityAlias::where('entity_id', $target->id)->where('alias', 'MSFT Cloud')->firstOrFail();
        $splitEntity = $service->splitEntity($target, [$aliasToSplit->id], 'MSFT Cloud Spin-off LLC', 'Spinning off cloud subsidiary into independent entity.', $this->admin->id);

        $this->assertInstanceOf(IntelligenceEntity::class, $splitEntity);
        $this->assertEquals('MSFT Cloud Spin-off LLC', $splitEntity->canonical_name);
        $this->assertEquals($splitEntity->id, $aliasToSplit->fresh()->entity_id);
    }

    /**
     * Test 4: Relationship Graph Creation, Multi-Level Querying and Verification
     */
    public function test_relationship_creation_graph_query_and_verification(): void
    {
        $entityService = app(EntityResolutionService::class);
        $graphService = app(RelationshipGraphService::class);

        $openai = $entityService->createEntity('OpenAI', 'company', ['verification_status' => 'verified']);
        $microsoft = $entityService->createEntity('Microsoft', 'company', ['verification_status' => 'verified']);
        $samAltman = $entityService->createEntity('Sam Altman', 'executive', ['verification_status' => 'verified']);

        // Create relationships
        $rel1 = $graphService->createRelationship([
            'source_entity_id' => $microsoft->id,
            'relationship_type' => 'partners_with',
            'target_entity_id' => $openai->id,
            'evidence_excerpt' => 'Microsoft announced a multi-billion dollar partnership extension with OpenAI.',
            'confidence_score' => 0.98,
            'verification_status' => 'verified',
        ]);

        $rel2 = $graphService->createRelationship([
            'source_entity_id' => $samAltman->id,
            'relationship_type' => 'employs',
            'target_entity_id' => $openai->id,
            'evidence_excerpt' => 'Sam Altman serves as Chief Executive Officer of OpenAI.',
            'confidence_score' => 0.95,
            'verification_status' => 'single_source',
        ]);

        // Direct relationships
        $direct = $graphService->getDirectRelationships($openai->id);
        $this->assertCount(2, $direct);

        // Multi-level graph
        $graph = $graphService->getMultiLevelGraph($samAltman->id, 2);
        $this->assertGreaterThanOrEqual(3, count($graph['nodes']));
        $this->assertGreaterThanOrEqual(2, count($graph['links']));

        // Verify relationship
        $graphService->verifyRelationship($rel2, $this->admin->id);
        $this->assertEquals('verified', $rel2->fresh()->verification_status);
    }

    /**
     * Test 5: Advanced Fact Verification with Syndication / Wire Copy Detection
     */
    public function test_advanced_fact_verification_with_syndication_detection(): void
    {
        $verification = app(AdvancedFactVerificationService::class);

        $fact = ExtractedFact::create([
            'source_id' => $this->source1->id,
            'claim' => 'Anthropic launched Claude 3.7 Sonnet featuring hybrid reasoning capabilities.',
            'entity' => 'Anthropic',
            'confidence_score' => 0.85,
            'verification_status' => 'supported_single',
            'evidence_excerpt' => 'Anthropic announced Claude 3.7 Sonnet today with hybrid reasoning.',
            'source_url' => 'https://reuters.example.com/claude37',
        ]);

        // Attach identical copy from second source (e.g. syndicated wire reprint)
        $evidence1 = $verification->attachEvidence(
            $fact,
            $this->source2,
            'Anthropic announced Claude 3.7 Sonnet today with hybrid reasoning.', // Exact identical wire wording
            'supports',
            'https://bloomberg.example.com/claude37'
        );

        $this->assertTrue($evidence1->is_syndicated_reprint, 'Identical wire repetition must be flagged as syndicated reprint');

        // Status must NOT falsely jump to supported_multiple because of a syndicated reprint!
        $this->assertEquals('supported_single', $fact->fresh()->verification_status);

        // Now attach truly independent confirmation with distinct reporting
        $independentSource = NewsSource::create([
            'name' => 'Wired Technology Review',
            'slug' => 'wired-technology-review',
            'url' => 'https://wired.example.com',
            'feed_url' => 'https://wired.example.com/feed.xml',
            'source_type' => 'RSS',
            'is_active' => true,
            'credibility_score' => 90.0,
            'trust_tier' => 'reputable_secondary',
            'source_category' => 'independent_reporting',
            'geographic_region' => 'US',
        ]);

        $evidence2 = $verification->attachEvidence(
            $fact,
            $independentSource,
            'Our hands-on benchmark tests confirmed Anthropic delivers dual reasoning modes in its Claude 3.7 release.',
            'supports',
            'https://wired.example.com/claude37-review'
        );

        $this->assertFalse($evidence2->is_syndicated_reprint);
        // Now it is verified across independent sources!
        $this->assertEquals('supported_multiple', $fact->fresh()->verification_status);
    }

    /**
     * Test 6: Fact Verification Conflicting Evidence and 10 Dimensions Evaluation
     */
    public function test_fact_verification_contradicting_evidence_and_dimensions(): void
    {
        $verification = app(AdvancedFactVerificationService::class);

        $fact = ExtractedFact::create([
            'source_id' => $this->source1->id,
            'claim' => 'TechCorp quarterly data center revenue surged 45% to $12 billion.',
            'entity' => 'TechCorp',
            'confidence_score' => 0.85,
            'verification_status' => 'supported_single',
            'evidence_excerpt' => 'TechCorp reported $12 billion data center revenue.',
            'source_url' => 'https://reuters.example.com/techcorp-earnings',
        ]);

        // Attach contradicting evidence from regulatory filing or investigative source
        $contradictingSource = NewsSource::create([
            'name' => 'Financial Times Investigative',
            'slug' => 'ft-investigative',
            'url' => 'https://ft.example.com',
            'feed_url' => 'https://ft.example.com/feed.xml',
            'source_type' => 'RSS',
            'is_active' => true,
            'credibility_score' => 96.0,
            'trust_tier' => 'established_primary',
            'source_category' => 'wire',
            'geographic_region' => 'GLOBAL',
        ]);

        $verification->attachEvidence(
            $fact,
            $contradictingSource,
            'SEC filings reveal TechCorp data center revenue was only $9.8 billion when accounting for deferred hardware revenue.',
            'contradicts',
            'https://ft.example.com/techcorp-discrepancy'
        );

        $fact->refresh();
        $this->assertEquals('conflicting', $fact->verification_status, 'Presence of contradicting evidence must flag claim as conflicting');

        // Check 10-dimension evaluation
        $dimensions = $verification->evaluateTenDimensions($fact, $fact->evidences, 1, true);
        $this->assertArrayHasKey('source_reliability', $dimensions);
        $this->assertArrayHasKey('source_independence', $dimensions);
        $this->assertArrayHasKey('reporting_consistency', $dimensions);
        $this->assertArrayHasKey('uncertainty_score', $dimensions);
        $this->assertLessThan(0.70, $dimensions['reporting_consistency'], 'Contradiction must drop consistency score');

        // Editorial manual verdict
        $verification->recordReviewerDecision($fact, 'disputed', 'SEC 10-K filing indicates deferred revenue discrepancies.', $this->admin->id);
        $this->assertEquals('disputed', $fact->fresh()->verification_status);
    }

    /**
     * Test 7: Historical Event Timeline and Date Corrections
     */
    public function test_historical_event_timeline_and_date_corrections(): void
    {
        $timeline = app(HistoricalTimelineService::class);
        $entityService = app(EntityResolutionService::class);

        $company = $entityService->createEntity('TSMC', 'company', ['verification_status' => 'verified']);

        // Event occurred on 2026-03-01 but was only reported publicly on 2026-03-05
        $event = $timeline->recordEvent([
            'title' => 'TSMC 2nm Production Fab Groundbreaking in Kaohsiung',
            'event_category' => 'technology_breakthrough',
            'summary' => 'TSMC officially began equipment installation for 2nm nanosheet process technology.',
            'occurred_at' => '2026-03-01 10:00:00',
            'reported_at' => '2026-03-05 14:00:00',
            'primary_entity_id' => $company->id,
        ]);

        $this->assertInstanceOf(IntelligenceEvent::class, $event);
        $this->assertEquals('2026-03-01 10:00:00', $event->occurred_at->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-03-05 14:00:00', $event->reported_at->format('Y-m-d H:i:s'));

        // Correct event date with audit trail
        $timeline->correctEventDate($event, '2026-02-28 09:00:00', 'Official government permit document establishes earlier date.', $this->admin->id);

        $event->refresh();
        $this->assertEquals('2026-02-28 09:00:00', $event->occurred_at->format('Y-m-d H:i:s'));
        $this->assertNotEmpty($event->metadata['date_corrections']);
    }

    /**
     * Test 8: Trend Intelligence Scoring Breakdown
     */
    public function test_trend_detection_scoring_breakdown(): void
    {
        $trendService = app(TrendIntelligenceService::class);

        // Create sample cluster
        $cluster = StoryCluster::create([
            'title' => 'Quantum Computing Error Mitigation Breakthrough',
            'slug' => 'quantum-computing-error-mitigation',
            'summary' => 'Physical qubits achieve 99.9% gate fidelity.',
            'story_count' => 3,
            'confidence_score' => 0.95,
        ]);

        $story = DiscoveredStory::create([
            'source_id' => $this->source1->id,
            'cluster_id' => $cluster->id,
            'title' => 'Quantum Error Mitigation Breakthrough Announced',
            'canonical_url' => 'https://reuters.example.com/quantum',
            'content_hash' => hash('sha256', 'quantum-1'),
            'summary' => 'Quantum breakthrough reported.',
            'published_at' => now(),
            'discovered_at' => now(),
        ]);
        $cluster->stories()->attach($story->id, ['is_primary' => true]);

        $detected = $trendService->detectEmergingTrends(7);
        $this->assertNotEmpty($detected);

        $firstTrend = $detected->first();
        $this->assertGreaterThan(0, $firstTrend->composite_score);
        $this->assertNotNull($firstTrend->volume_score);
        $this->assertNotNull($firstTrend->growth_rate_score);
        $this->assertNotNull($firstTrend->source_diversity_score);
        $this->assertNotNull($firstTrend->source_credibility_score);
    }

    /**
     * Test 9: Deep Research Engine Execution, Citations Grounding and Review Workflow
     */
    public function test_deep_research_execution_grounding_and_citations(): void
    {
        $researchService = app(DeepResearchEngineService::class);
        $entityService = app(EntityResolutionService::class);

        $intel = $entityService->createEntity('Intel Foundry Services', 'company', ['verification_status' => 'verified']);

        // Seed supporting facts
        $fact1 = ExtractedFact::create([
            'source_id' => $this->source1->id,
            'claim' => 'Intel 18A process node entered commercial high-volume manufacturing.',
            'entity' => 'Intel Foundry Services',
            'confidence_score' => 0.96,
            'verification_status' => 'supported_multiple',
            'evidence_excerpt' => 'Intel confirmed 18A high-volume manufacturing at Fab 52.',
            'source_url' => 'https://reuters.example.com/intel-18a',
            'published_at' => now()->subDays(2),
        ]);

        $project = ResearchProject::create([
            'user_id' => $this->admin->id,
            'title' => 'Commercial Feasibility of Intel 18A Process Node',
            'research_question' => 'Evaluate Intel 18A manufacturing yield, customer adoption, and enterprise market viability.',
            'topic' => 'Semiconductors',
            'entity_id' => $intel->id,
            'depth' => 'comprehensive',
            'output_format' => 'technology_assessment',
            'status' => 'queued',
        ]);

        $report = $researchService->executeResearch($project);

        $this->assertInstanceOf(ResearchReport::class, $report);
        $this->assertEquals('in_review', $report->editorial_status);
        $this->assertFalse($report->is_public);
        $this->assertNotEmpty($report->executive_summary);
        $this->assertNotEmpty($report->content_markdown);

        // Grounded citations verification
        $citations = $report->citations;
        $this->assertNotEmpty($citations);
        $firstCitation = $citations->first();
        $this->assertEquals($fact1->id, $firstCitation->fact_id);
        $this->assertEquals($this->source1->name, $firstCitation->source_name);
    }

    /**
     * Test 10: Admin Research Control Center API Endpoints
     */
    public function test_admin_research_api_endpoints(): void
    {
        // 1. Overview
        $resOverview = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/research');
        $resOverview->assertStatus(200)->assertJsonStructure(['success', 'data' => ['metrics']]);

        // 2. Entities CRUD
        $resStore = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/research/entities', [
            'canonical_name' => 'Mistral AI',
            'entity_type' => 'company',
            'country' => 'FR',
            'verification_status' => 'verified',
        ]);
        $resStore->assertStatus(201);
        $entityId = $resStore->json('data.id');

        $resList = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/research/entities');
        $resList->assertStatus(200);

        // 3. Relationships API
        $resTarget = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/research/entities', [
            'canonical_name' => 'Mistral Large',
            'entity_type' => 'product',
        ]);
        $targetId = $resTarget->json('data.id');

        $resRel = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/research/relationships', [
            'source_entity_id' => $entityId,
            'relationship_type' => 'develops',
            'target_entity_id' => $targetId,
            'confidence_score' => 0.99,
        ]);
        $resRel->assertStatus(201);

        // 4. Graph API
        $resGraph = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/research/graph?root_id=' . $entityId);
        $resGraph->assertStatus(200)->assertJsonStructure(['success', 'data' => ['nodes', 'links']]);

        // 5. Timeline API
        $resTimeline = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/research/timeline', [
            'title' => 'Mistral Large Model Architecture Unveiled',
            'event_category' => 'technology_breakthrough',
            'occurred_at' => now()->toIso8601String(),
            'primary_entity_id' => $entityId,
        ]);
        $resTimeline->assertStatus(201);

        // 6. Deep Research Workspace API
        $resResearch = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/research/workspace/projects', [
            'title' => 'Mistral Enterprise MoE Scaling Analysis',
            'research_question' => 'Analyze sparse mixture of experts architectural efficiency.',
            'depth' => 'standard',
            'output_format' => 'technology_assessment',
            'entity_id' => $entityId,
        ]);
        $resResearch->assertStatus(201);
    }

    /**
     * Test 11: Public Intelligence API Endpoints (Exposing Only Verified Data)
     */
    public function test_public_intelligence_api_endpoints(): void
    {
        $entityService = app(EntityResolutionService::class);

        $company = $entityService->createEntity('Databricks', 'company', [
            'legal_name' => 'Databricks Incorporated',
            'verification_status' => 'verified',
            'website_url' => 'https://databricks.example.com',
            'country' => 'US',
        ]);

        // 1. Public Company Profile
        $resCompany = $this->getJson('/api/v1/intelligence/companies/' . $company->slug);
        $resCompany->assertStatus(200)
            ->assertJsonPath('data.canonical_name', 'Databricks')
            ->assertJsonPath('data.verification_status', 'verified');

        // 2. Public Event Timeline
        $timeline = app(HistoricalTimelineService::class);
        $timeline->recordEvent([
            'title' => 'Databricks Announces Lakebase Autonomous Engine',
            'event_category' => 'technology_breakthrough',
            'summary' => 'Public preview of next-gen lakehouse data warehouse.',
            'occurred_at' => now(),
            'verification_status' => 'confirmed',
            'primary_entity_id' => $company->id,
        ]);

        $resTimeline = $this->getJson('/api/v1/intelligence/timeline');
        $resTimeline->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }
}
