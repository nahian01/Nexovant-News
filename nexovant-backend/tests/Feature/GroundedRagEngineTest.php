<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeRetrievalLog;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\SemanticCache;
use App\Models\User;
use App\Services\RAG\GroundedRagService;
use App\Services\RAG\KnowledgeBaseService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroundedRagEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Organization $orgAlpha;
    protected Organization $orgBeta;
    protected User $userAlpha;
    protected User $userBeta;
    protected User $publicUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        // Admin User from Seeder
        $this->adminUser = User::where('email', 'admin@nexovant.si')->firstOrFail();

        // Organizations
        $this->orgAlpha = Organization::create([
            'name' => 'Alpha Capital Research',
            'slug' => 'alpha-capital',
            'created_by' => $this->adminUser->id,
        ]);

        $this->orgBeta = Organization::create([
            'name' => 'Beta Quantum Dynamics',
            'slug' => 'beta-quantum',
            'created_by' => $this->adminUser->id,
        ]);

        // Organization Alpha User
        $this->userAlpha = User::factory()->create([
            'account_type' => 'individual',
        ]);
        OrganizationMember::create([
            'organization_id' => $this->orgAlpha->id,
            'user_id' => $this->userAlpha->id,
            'role' => 'member',
        ]);

        // Organization Beta User
        $this->userBeta = User::factory()->create([
            'account_type' => 'individual',
        ]);
        OrganizationMember::create([
            'organization_id' => $this->orgBeta->id,
            'user_id' => $this->userBeta->id,
            'role' => 'member',
        ]);

        // Public / Individual User without Organization
        $this->publicUser = User::factory()->create([
            'account_type' => 'individual',
        ]);
    }

    public function test_document_ingestion_and_chunk_segmentation(): void
    {
        $knowledgeService = app(KnowledgeBaseService::class);

        $sampleText = "ASML and TSMC have reached a preliminary agreement on next-generation High-NA EUV lithography tooling deployment across European fab lines. The capital expenditure footprint is projected at 4.2 billion euros.\n\n" .
            "This initiative strengthens the European Union Chips Act mandate to elevate domestic wafer fabrication capacity above 20% of global supply by 2030.\n\n" .
            "Leading semiconductor research centers including IMEC and CEA-Leti are participating in the pilot line characterization phase.";

        $doc = $knowledgeService->ingestDocument(
            title: 'High-NA EUV European Deployment Roadmap',
            content: $sampleText,
            documentType: 'technical_paper',
            tenantId: null,
            sourceUrl: 'https://nexovant.io/intelligence/high-na-euv-deployment'
        );

        $this->assertInstanceOf(KnowledgeDocument::class, $doc);
        $this->assertNotEmpty($doc->id);
        $this->assertGreaterThan(0, $doc->chunk_count);
        $this->assertNull($doc->tenant_id);
        $this->assertEquals('technical_paper', $doc->document_type);

        // Verify Chunks
        $chunks = KnowledgeChunk::where('document_id', $doc->id)->get();
        $this->assertCount($doc->chunk_count, $chunks);

        foreach ($chunks as $chunk) {
            $this->assertGreaterThan(0, $chunk->token_count);
            $this->assertNotEmpty($chunk->content_hash);
            $this->assertNull($chunk->tenant_id);

            // Verify Embeddings
            $embeddings = $chunk->embeddings;
            $this->assertNotEmpty($embeddings);
            $this->assertEquals(1536, $embeddings->first()->dimensions);
            $this->assertIsArray($embeddings->first()->embedding);
        }
    }

    public function test_strict_multi_tenant_isolation_boundary(): void
    {
        $knowledgeService = app(KnowledgeBaseService::class);
        $ragService = app(GroundedRagService::class);

        // 1. Ingest Private Alpha Document
        $docAlpha = $knowledgeService->ingestDocument(
            title: 'Alpha Private Acquisition Strategy',
            content: 'Alpha Capital has finalized exclusive term sheets to acquire Zurich Silicon Photonics for 340M CHF.',
            documentType: 'financial_disclosure',
            tenantId: $this->orgAlpha->id
        );

        // 2. Ingest Private Beta Document
        $docBeta = $knowledgeService->ingestDocument(
            title: 'Beta Quantum Cryogenics Blueprint',
            content: 'Beta Quantum Dynamics has patented a dilution refrigeration subsystem operating at 12 millikelvin.',
            documentType: 'patent_intelligence',
            tenantId: $this->orgBeta->id
        );

        // 3. Ingest Public Platform Document
        $docPublic = $knowledgeService->ingestDocument(
            title: 'Global Semiconductor Substrate Supply Overview',
            content: 'Gallium nitride and silicon carbide substrate volumes increased 24 percent year over year across automotive applications.',
            documentType: 'article',
            tenantId: null
        );

        // TEST 1: User Alpha queries about Alpha private topic
        $resAlpha = $ragService->query(
            queryText: 'What are Alpha Capital acquisition terms?',
            tenantId: $this->orgAlpha->id,
            userId: $this->userAlpha->id
        );
        $this->assertTrue($resAlpha['success']);
        $this->assertFalse($resAlpha['cache_hit']);
        $alphaChunkIds = collect($resAlpha['source_citations'])->pluck('chunk_id')->all();
        $alphaDocIds = KnowledgeChunk::whereIn('id', $alphaChunkIds)->pluck('document_id')->all();
        $this->assertContains($docAlpha->id, $alphaDocIds);

        // TEST 2: User Beta queries for Alpha topic -> Must NOT find Alpha private document
        $resBetaOnAlpha = $ragService->query(
            queryText: 'What are Alpha Capital acquisition terms?',
            tenantId: $this->orgBeta->id,
            userId: $this->userBeta->id
        );
        $this->assertTrue($resBetaOnAlpha['success']);
        $betaChunkIds = collect($resBetaOnAlpha['source_citations'])->pluck('chunk_id')->all();
        $betaDocIds = KnowledgeChunk::whereIn('id', $betaChunkIds)->pluck('document_id')->all();
        $this->assertNotContains($docAlpha->id, $betaDocIds, 'Tenant isolation violation: Org Beta accessed Org Alpha private documents!');

        // TEST 3: Public User queries for Alpha topic -> Must NEVER access Org Alpha private documents
        $resPublicOnAlpha = $ragService->query(
            queryText: 'What are Alpha Capital acquisition terms?',
            tenantId: null,
            userId: $this->publicUser->id
        );
        $this->assertTrue($resPublicOnAlpha['success']);
        $publicChunkIds = collect($resPublicOnAlpha['source_citations'])->pluck('chunk_id')->all();
        $publicDocIds = KnowledgeChunk::whereIn('id', $publicChunkIds)->pluck('document_id')->all();
        $this->assertNotContains($docAlpha->id, $publicDocIds, 'Tenant isolation violation: Public user accessed Org Alpha private documents!');
        $this->assertNotContains($docBeta->id, $publicDocIds, 'Tenant isolation violation: Public user accessed Org Beta private documents!');

        // TEST 4: Both User Alpha and User Beta can access Public Knowledge
        $resAlphaPublic = $ragService->query(
            queryText: 'Gallium nitride silicon carbide substrate volumes',
            tenantId: $this->orgAlpha->id,
            userId: $this->userAlpha->id
        );
        $alphaPubDocIds = KnowledgeChunk::whereIn('id', collect($resAlphaPublic['source_citations'])->pluck('chunk_id'))->pluck('document_id')->all();
        $this->assertContains($docPublic->id, $alphaPubDocIds);
    }

    public function test_semantic_cache_isolation_and_hit(): void
    {
        $knowledgeService = app(KnowledgeBaseService::class);
        $ragService = app(GroundedRagService::class);

        $knowledgeService->ingestDocument(
            title: 'Foundry Capital Expenditures Report',
            content: 'Global semiconductor capex exceeded 145 billion dollars driven by advanced packaging investments.',
            documentType: 'article',
            tenantId: $this->orgAlpha->id
        );

        $query = 'What is the foundry capex trend for advanced packaging?';

        // 1. First execution for User Alpha (Cache Miss)
        $firstCall = $ragService->query($query, $this->orgAlpha->id, $this->userAlpha->id);
        $this->assertTrue($firstCall['success']);
        $this->assertFalse($firstCall['cache_hit']);

        // Verify cache record was created with tenant_id
        $queryHash = hash('sha256', strtolower($query));
        $cacheEntry = SemanticCache::where('query_hash', $queryHash)->first();
        $this->assertNotNull($cacheEntry);
        $this->assertEquals($this->orgAlpha->id, $cacheEntry->tenant_id);

        // 2. Second execution for User Alpha (Cache Hit)
        $secondCall = $ragService->query($query, $this->orgAlpha->id, $this->userAlpha->id);
        $this->assertTrue($secondCall['success']);
        $this->assertTrue($secondCall['cache_hit']);
        $this->assertEquals('semantic_cache', $secondCall['meta']['retrieval_method']);

        // 3. Exact same query from User Beta -> Cache Miss (strict tenant isolation!)
        $betaCall = $ragService->query($query, $this->orgBeta->id, $this->userBeta->id);
        $this->assertTrue($betaCall['success']);
        $this->assertFalse($betaCall['cache_hit'], 'Tenant cache isolation violation: Org Beta hit Org Alpha cached entry!');

        // 4. Exact same query from Public User -> Cache Miss (strict tenant isolation!)
        $pubCall = $ragService->query($query, null, $this->publicUser->id);
        $this->assertTrue($pubCall['success']);
        $this->assertFalse($pubCall['cache_hit'], 'Tenant cache isolation violation: Public user hit Org Alpha cached entry!');
    }

    public function test_document_reindex_and_cache_purging(): void
    {
        $knowledgeService = app(KnowledgeBaseService::class);

        $doc = $knowledgeService->ingestDocument(
            title: 'Initial Version Bio',
            content: 'Executive Jensen Huang founded NVIDIA in 1993.',
            documentType: 'executive_bio',
            tenantId: null
        );

        $initialHash = $doc->content_version;

        // Reindex with new expanded content
        $updatedDoc = $knowledgeService->reindexDocument(
            $doc->id,
            "Executive Jensen Huang founded NVIDIA in 1993.\n\nUnder his leadership, the company pioneered GPU accelerated computing and revolutionized AI computing architectures."
        );

        $this->assertNotEquals($initialHash, $updatedDoc->content_version);
        $this->assertGreaterThanOrEqual(1, $updatedDoc->chunk_count);

        // Test cache purge
        SemanticCache::create([
            'tenant_id' => $this->orgAlpha->id,
            'query_hash' => hash('sha256', 'test query'),
            'query_text' => 'test query',
            'response_text' => 'cached answer',
            'expires_at' => now()->addDay(),
        ]);

        $this->assertEquals(1, SemanticCache::count());
        $purged = $knowledgeService->clearCache($this->orgAlpha->id);
        $this->assertEquals(1, $purged);
        $this->assertEquals(0, SemanticCache::count());
    }

    public function test_admin_rag_endpoints_and_telemetry(): void
    {
        $knowledgeService = app(KnowledgeBaseService::class);

        $doc = $knowledgeService->ingestDocument(
            title: 'Admin Telemetry Reference Doc',
            content: 'Monitored EUV manufacturing and advanced packaging nodes.',
            documentType: 'technical_paper',
            tenantId: null
        );

        // 1. GET /api/v1/admin/rag-knowledge (Index & Telemetry)
        $res = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/rag-knowledge');
        $res->assertOk();
        $res->assertJsonStructure([
            'success',
            'stats' => [
                'total_documents',
                'total_chunks',
                'cache_hit_ratio',
                'average_context_size',
                'vector_index_type',
                'retrieval_latency_ms',
                'token_savings_24h',
            ],
            'documents',
        ]);

        // 2. POST /api/v1/admin/rag-knowledge/ingest
        $ingestRes = $this->actingAs($this->adminUser, 'sanctum')->postJson('/api/v1/admin/rag-knowledge/ingest', [
            'title' => 'Ingested from Admin API',
            'document_type' => 'regulatory_filing',
            'content' => 'Form 10-K audited annual report for international semiconductor operations.',
            'source_url' => 'https://sec.gov/edgar/sample-10k',
        ]);
        $ingestRes->assertCreated();
        $newDocId = $ingestRes->json('document.id');
        $this->assertNotNull($newDocId);

        // 3. GET /api/v1/admin/rag-knowledge/documents/{id}/chunks
        $chunksRes = $this->actingAs($this->adminUser, 'sanctum')->getJson("/api/v1/admin/rag-knowledge/documents/{$newDocId}/chunks");
        $chunksRes->assertOk();
        $chunksRes->assertJsonStructure([
            'success',
            'document',
            'chunks',
        ]);

        // 4. POST /api/v1/admin/rag-knowledge/{id}/reindex
        $reindexRes = $this->actingAs($this->adminUser, 'sanctum')->postJson("/api/v1/admin/rag-knowledge/{$newDocId}/reindex");
        $reindexRes->assertOk();
        $this->assertTrue($reindexRes->json('success'));

        // 5. POST /api/v1/admin/rag-knowledge/clear-cache
        $clearRes = $this->actingAs($this->adminUser, 'sanctum')->postJson('/api/v1/admin/rag-knowledge/clear-cache');
        $clearRes->assertOk();
        $this->assertTrue($clearRes->json('success'));

        // 6. DELETE /api/v1/admin/rag-knowledge/{id}
        $delRes = $this->actingAs($this->adminUser, 'sanctum')->deleteJson("/api/v1/admin/rag-knowledge/{$newDocId}");
        $delRes->assertOk();
        $this->assertDatabaseMissing('knowledge_documents', ['id' => $newDocId]);
    }

    public function test_rag_retrieval_logging_and_subscriber_endpoint(): void
    {
        $knowledgeService = app(KnowledgeBaseService::class);

        $knowledgeService->ingestDocument(
            title: 'Subscriber Monitored Tech Hub',
            content: 'Cambridge Silicon Silicon Fen maintains deep AI hardware research clusters.',
            documentType: 'article',
            tenantId: $this->orgAlpha->id
        );

        $initialLogCount = KnowledgeRetrievalLog::count();

        // Query via authenticated subscriber endpoint
        $response = $this->actingAs($this->userAlpha, 'sanctum')->postJson('/api/v1/rag/query', [
            'query' => 'What research clusters exist in Cambridge Silicon Fen?',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'response',
            'source_citations',
            'cache_hit',
            'meta' => [
                'retrieval_method',
                'latency_ms',
            ],
        ]);

        // Verify log was saved in database
        $this->assertEquals($initialLogCount + 1, KnowledgeRetrievalLog::count());
        $latestLog = KnowledgeRetrievalLog::latest('created_at')->first();
        $this->assertNotNull($latestLog);
        $this->assertEquals($this->userAlpha->id, $latestLog->user_id);
        $this->assertEquals($this->orgAlpha->id, $latestLog->tenant_id);
        $this->assertFalse($latestLog->cache_hit);
    }
}
