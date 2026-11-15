<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\ClaimEvidence;
use App\Models\Company;
use App\Models\EntityAlias;
use App\Models\EntityRelationship;
use App\Models\EntityResolutionReview;
use App\Models\Executive;
use App\Models\ExtractedFact;
use App\Models\IntelligenceEntity;
use App\Models\IntelligenceEvent;
use App\Models\IntelligenceTrend;
use App\Models\NewsSource;
use App\Models\Product;
use App\Models\ResearchProject;
use App\Models\ResearchReport;
use App\Models\Technology;
use App\Services\Research\AdvancedFactVerificationService;
use App\Services\Research\DeepResearchEngineService;
use App\Services\Research\EntityResolutionService;
use App\Services\Research\HistoricalTimelineService;
use App\Services\Research\RelationshipGraphService;
use App\Services\Research\TrendIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResearchAdminController
{
    public function __construct(
        protected EntityResolutionService $entityService,
        protected RelationshipGraphService $graphService,
        protected AdvancedFactVerificationService $verificationService,
        protected HistoricalTimelineService $timelineService,
        protected TrendIntelligenceService $trendService,
        protected DeepResearchEngineService $researchService
    ) {
    }

    /**
     * 1. Overview Dashboard Metrics
     */
    public function overview(): JsonResponse
    {
        $metrics = [
            'total_entities' => IntelligenceEntity::where('verification_status', '!=', 'merged')->count(),
            'verified_entities' => IntelligenceEntity::where('verification_status', 'verified')->count(),
            'pending_reviews' => EntityResolutionReview::where('status', 'pending')->count(),
            'total_relationships' => EntityRelationship::where('is_active', true)->count(),
            'verified_claims' => ExtractedFact::whereIn('verification_status', ['supported_multiple', 'verified_by_editor'])->count(),
            'disputed_claims' => ExtractedFact::where('verification_status', 'conflicting')->count(),
            'active_trends' => IntelligenceTrend::count(),
            'research_reports' => ResearchReport::count(),
            'recent_events_count' => IntelligenceEvent::where('occurred_at', '>=', now()->subDays(30))->count(),
        ];

        $recentReports = ResearchReport::with('project')->latest()->limit(5)->get();
        $recentEvents = IntelligenceEvent::with('primaryEntity')->latest('occurred_at')->limit(5)->get();
        $topTrends = IntelligenceTrend::orderByDesc('composite_score')->limit(5)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'metrics' => $metrics,
                'recent_reports' => $recentReports,
                'recent_events' => $recentEvents,
                'top_trends' => $topTrends,
            ],
        ]);
    }

    /**
     * 2. Entities: List & Filter
     */
    public function listEntities(Request $request): JsonResponse
    {
        $query = IntelligenceEntity::query()->where('verification_status', '!=', 'merged');

        if ($request->filled('type')) {
            $query->where('entity_type', $request->query('type'));
        }

        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->query('verification_status'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('canonical_name', 'like', $search)
                  ->orWhere('legal_name', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        $entities = $query->withCount(['aliases', 'outgoingRelationships', 'incomingRelationships'])
            ->orderBy('canonical_name')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $entities,
        ]);
    }

    /**
     * Entities: Create
     */
    public function storeEntity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'canonical_name' => 'required|string|max:255',
            'entity_type' => 'required|string|in:company,executive,product,technology,industry,institution,investor,government,region,event',
            'legal_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'website_url' => 'nullable|url|max:255',
            'country' => 'nullable|string|max:10',
            'headquarters' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:100',
            'founding_year' => 'nullable|integer',
            'verification_status' => 'nullable|string|in:verified,unverified',
            'aliases' => 'nullable|array',
            'aliases.*' => 'string|max:255',
        ]);

        $entity = $this->entityService->createEntity($validated['canonical_name'], $validated['entity_type'], [
            'legal_name' => $validated['legal_name'] ?? null,
            'description' => $validated['description'] ?? null,
            'website_url' => $validated['website_url'] ?? null,
            'country' => $validated['country'] ?? null,
            'headquarters' => $validated['headquarters'] ?? null,
            'industry' => $validated['industry'] ?? null,
            'founding_year' => $validated['founding_year'] ?? null,
            'verification_status' => $validated['verification_status'] ?? 'unverified',
            'user_id' => $request->user()?->id,
        ]);

        if (!empty($validated['aliases'])) {
            foreach ($validated['aliases'] as $alias) {
                $entity->addAlias($alias, 'alternative_name');
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Intelligence entity created successfully.',
            'data' => $entity->fresh('aliases'),
        ], 201);
    }

    /**
     * Entities: Show
     */
    public function showEntity(string $id): JsonResponse
    {
        $entity = IntelligenceEntity::with([
            'aliases',
            'outgoingRelationships.targetEntity',
            'incomingRelationships.sourceEntity',
            'histories.user',
            'events',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $entity,
        ]);
    }

    /**
     * Entities: Update
     */
    public function updateEntity(Request $request, string $id): JsonResponse
    {
        $entity = IntelligenceEntity::findOrFail($id);

        $validated = $request->validate([
            'canonical_name' => 'sometimes|required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'website_url' => 'nullable|url|max:255',
            'country' => 'nullable|string|max:10',
            'headquarters' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:100',
            'founding_year' => 'nullable|integer',
            'verification_status' => 'nullable|string|in:verified,unverified,disputed,archived',
            'metadata' => 'nullable|array',
        ]);

        $entity->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Entity updated successfully.',
            'data' => $entity->fresh(),
        ]);
    }

    /**
     * Entities: Verify
     */
    public function verifyEntity(Request $request, string $id): JsonResponse
    {
        $entity = IntelligenceEntity::findOrFail($id);
        $this->entityService->verifyEntity($entity, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Entity confirmed and verified.',
            'data' => $entity->fresh(),
        ]);
    }

    /**
     * Entities: Dispute
     */
    public function disputeEntity(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $entity = IntelligenceEntity::findOrFail($id);
        $this->entityService->disputeEntity($entity, $validated['reason'], $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Entity marked as disputed.',
            'data' => $entity->fresh(),
        ]);
    }

    /**
     * Entities: Merge
     */
    public function mergeEntities(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'source_entity_id' => 'required|uuid|exists:intelligence_entities,id',
            'reason' => 'required|string|max:500',
        ]);

        $target = IntelligenceEntity::findOrFail($id);
        $source = IntelligenceEntity::findOrFail($validated['source_entity_id']);

        if ($target->id === $source->id) {
            return response()->json(['success' => false, 'message' => 'Target and source entity must be distinct.'], 422);
        }

        $merged = $this->entityService->mergeEntities($target, $source, $validated['reason'], $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => "Successfully merged {$source->canonical_name} into {$target->canonical_name}.",
            'data' => $merged,
        ]);
    }

    /**
     * Entities: Split
     */
    public function splitEntity(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'alias_ids' => 'required|array|min:1',
            'alias_ids.*' => 'uuid|exists:entity_aliases,id',
            'new_canonical_name' => 'required|string|max:255',
            'reason' => 'required|string|max:500',
        ]);

        $entity = IntelligenceEntity::findOrFail($id);
        $newEntity = $this->entityService->splitEntity(
            $entity,
            $validated['alias_ids'],
            $validated['new_canonical_name'],
            $validated['reason'],
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => "Successfully split into new entity {$newEntity->canonical_name}.",
            'data' => $newEntity,
        ]);
    }

    /**
     * Entities: Review Queue
     */
    public function listReviewQueue(): JsonResponse
    {
        $queue = EntityResolutionReview::with(['entity', 'potentialMatch'])
            ->where('status', 'pending')
            ->orderByDesc('confidence_score')
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $queue,
        ]);
    }

    /**
     * Entities: Review Decision
     */
    public function reviewDecision(Request $request, string $reviewId): JsonResponse
    {
        $validated = $request->validate([
            'decision' => 'required|in:merge,reject,keep_distinct',
            'reason' => 'nullable|string|max:500',
        ]);

        $review = EntityResolutionReview::findOrFail($reviewId);

        if ($validated['decision'] === 'merge' && $review->entity && $review->potentialMatch) {
            $this->entityService->mergeEntities(
                $review->potentialMatch,
                $review->entity,
                $validated['reason'] ?? 'Approved via resolution review queue',
                $request->user()?->id
            );
            $review->update(['status' => 'merged', 'reviewed_by_user_id' => $request->user()?->id, 'reviewed_at' => now()]);
        } else {
            $review->update([
                'status' => $validated['decision'] === 'reject' ? 'rejected' : 'split_as_new',
                'reviewed_by_user_id' => $request->user()?->id,
                'reviewed_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Review decision recorded.',
            'data' => $review->fresh(),
        ]);
    }

    /**
     * 3. Company Intelligence Profiles
     */
    public function listCompanies(Request $request): JsonResponse
    {
        $query = IntelligenceEntity::where('entity_type', 'company')
            ->where('verification_status', '!=', 'merged')
            ->withCount(['outgoingRelationships as products_count' => fn($q) => $q->where('relationship_type', 'develops')])
            ->withCount(['outgoingRelationships as competitors_count' => fn($q) => $q->where('relationship_type', 'competes_with')]);

        if ($request->filled('search')) {
            $query->where('canonical_name', 'like', '%' . $request->query('search') . '%');
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->integer('per_page', 20)),
        ]);
    }

    /**
     * 4. Executive Intelligence
     */
    public function listExecutives(Request $request): JsonResponse
    {
        $query = IntelligenceEntity::where('entity_type', 'executive')
            ->where('verification_status', '!=', 'merged')
            ->with(['outgoingRelationships.targetEntity']);

        if ($request->filled('search')) {
            $query->where('canonical_name', 'like', '%' . $request->query('search') . '%');
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->integer('per_page', 20)),
        ]);
    }

    /**
     * 5. Product & Technology Intelligence
     */
    public function listTechnology(Request $request): JsonResponse
    {
        $query = IntelligenceEntity::whereIn('entity_type', ['product', 'technology'])
            ->where('verification_status', '!=', 'merged')
            ->with(['incomingRelationships.sourceEntity']);

        if ($request->filled('type')) {
            $query->where('entity_type', $request->query('type'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->integer('per_page', 20)),
        ]);
    }

    /**
     * 6. Relationship Explorer & Graph Visualizer
     */
    public function getGraph(Request $request): JsonResponse
    {
        $rootId = $request->query('root_id');

        if (!$rootId) {
            // Pick prominent company or entity as default
            $root = IntelligenceEntity::where('entity_type', 'company')
                ->where('verification_status', 'verified')
                ->first() ?: IntelligenceEntity::first();
            $rootId = $root?->id;
        }

        if (!$rootId) {
            return response()->json([
                'success' => true,
                'data' => ['nodes' => [], 'links' => [], 'metadata' => []],
            ]);
        }

        $depth = $request->integer('depth', 2);
        $filters = [
            'relationship_type' => $request->query('relationship_type'),
            'verification_status' => $request->query('verification_status'),
            'min_confidence' => $request->query('min_confidence'),
        ];

        $graph = $this->graphService->getMultiLevelGraph((string) $rootId, $depth, $filters);

        return response()->json([
            'success' => true,
            'data' => $graph,
        ]);
    }

    /**
     * Create Relationship
     */
    public function storeRelationship(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_entity_id' => 'required|uuid|exists:intelligence_entities,id',
            'relationship_type' => 'required|string|in:owns,develops,acquires,partners_with,invests_in,competes_with,employs,founded_by,uses_technology,supplies,subsidiary_of,parent_of,operates_in,announced,collaborates_with',
            'target_entity_id' => 'required|uuid|exists:intelligence_entities,id',
            'evidence_excerpt' => 'nullable|string',
            'source_url' => 'nullable|url',
            'supporting_source_id' => 'nullable|uuid|exists:news_sources,id',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date',
            'confidence_score' => 'nullable|numeric|between:0.0,1.0',
            'verification_status' => 'nullable|in:verified,single_source,ai_inferred,disputed',
        ]);

        $rel = $this->graphService->createRelationship($validated);

        return response()->json([
            'success' => true,
            'message' => 'Relationship created successfully.',
            'data' => $rel->load(['sourceEntity', 'targetEntity', 'supportingSource']),
        ], 201);
    }

    /**
     * Verify Relationship
     */
    public function verifyRelationship(Request $request, string $id): JsonResponse
    {
        $rel = EntityRelationship::findOrFail($id);
        $this->graphService->verifyRelationship($rel, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Relationship verified.',
            'data' => $rel->fresh(),
        ]);
    }

    /**
     * 7. Fact Verification Center
     */
    public function listFacts(Request $request): JsonResponse
    {
        $query = ExtractedFact::with(['source', 'evidences.source', 'histories.user'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('verification_status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('claim', 'like', $search)
                  ->orWhere('entity', 'like', $search);
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->integer('per_page', 20)),
        ]);
    }

    /**
     * Attach Evidence to Claim
     */
    public function attachFactEvidence(Request $request, string $factId): JsonResponse
    {
        $fact = ExtractedFact::findOrFail($factId);

        $validated = $request->validate([
            'source_id' => 'required|uuid|exists:news_sources,id',
            'evidence_text' => 'required|string|min:5',
            'stance' => 'required|in:supports,contradicts,neutral,refines',
            'source_url' => 'nullable|url',
        ]);

        $source = NewsSource::findOrFail($validated['source_id']);

        $evidence = $this->verificationService->attachEvidence(
            $fact,
            $source,
            $validated['evidence_text'],
            $validated['stance'],
            $validated['source_url'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Evidence attached and claim status re-evaluated.',
            'data' => [
                'evidence' => $evidence,
                'fact' => $fact->fresh(['evidences']),
            ],
        ]);
    }

    /**
     * Record Fact Verdict
     */
    public function recordFactVerdict(Request $request, string $factId): JsonResponse
    {
        $fact = ExtractedFact::findOrFail($factId);

        $validated = $request->validate([
            'status' => 'required|in:verified_by_editor,disputed,rejected',
            'reason' => 'required|string|max:500',
        ]);

        $this->verificationService->recordReviewerDecision(
            $fact,
            $validated['status'],
            $validated['reason'],
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Editorial verdict recorded.',
            'data' => $fact->fresh(['evidences', 'histories']),
        ]);
    }

    /**
     * 8. Event Timeline
     */
    public function listTimeline(Request $request): JsonResponse
    {
        $filters = [
            'event_category' => $request->query('event_category'),
            'verification_status' => $request->query('verification_status'),
            'search' => $request->query('search'),
            'limit' => $request->integer('limit', 50),
        ];

        $events = $this->timelineService->getGlobalTimeline($filters);

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Record Event
     */
    public function storeEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'event_category' => 'required|string|in:product_launch,acquisition,executive_change,benchmark,funding_round,partnership,regulatory,technology_breakthrough',
            'summary' => 'nullable|string',
            'occurred_at' => 'required|date',
            'reported_at' => 'nullable|date',
            'primary_entity_id' => 'nullable|uuid|exists:intelligence_entities,id',
            'verification_status' => 'nullable|in:confirmed,reported_reliable,developing,disputed',
        ]);

        $event = $this->timelineService->recordEvent($validated);

        return response()->json([
            'success' => true,
            'message' => 'Historical event recorded on timeline.',
            'data' => $event,
        ], 201);
    }

    /**
     * Correct Event Date
     */
    public function correctEventDate(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'occurred_at' => 'required|date',
            'reason' => 'required|string|max:500',
        ]);

        $event = IntelligenceEvent::findOrFail($id);
        $this->timelineService->correctEventDate($event, $validated['occurred_at'], $validated['reason'], $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Event occurred date updated with audit record.',
            'data' => $event->fresh(),
        ]);
    }

    /**
     * 9. Trend Intelligence
     */
    public function listTrends(Request $request): JsonResponse
    {
        $filters = [
            'trend_category' => $request->query('trend_category'),
            'status' => $request->query('status'),
            'min_score' => $request->query('min_score'),
        ];

        $trends = $this->trendService->getTrendingSignals($filters);

        return response()->json([
            'success' => true,
            'data' => $trends,
        ]);
    }

    /**
     * Trigger On-Demand Trend Scan
     */
    public function triggerTrendScan(): JsonResponse
    {
        $trends = $this->trendService->detectEmergingTrends(7);

        return response()->json([
            'success' => true,
            'message' => sprintf('Trend scan complete. %d trends updated or detected.', $trends->count()),
            'data' => $trends,
        ]);
    }

    /**
     * 10. Deep Research Workspace & Reports
     */
    public function listResearchProjects(): JsonResponse
    {
        $projects = ResearchProject::with(['entity', 'report'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    /**
     * Initiate Deep Research Project
     */
    public function initiateResearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'research_question' => 'required|string|min:10',
            'topic' => 'nullable|string|max:100',
            'entity_id' => 'nullable|uuid|exists:intelligence_entities,id',
            'depth' => 'nullable|in:brief,standard,comprehensive,exhaustive',
            'output_format' => 'nullable|in:executive_summary,company_profile,technology_assessment,industry_analysis,competitive_intelligence,executive_profile,product_comparison,historical_event_analysis,trend_report,custom_research',
            'time_range_start' => 'nullable|date',
            'time_range_end' => 'nullable|date',
            'geographic_region' => 'nullable|string|max:20',
        ]);

        $project = ResearchProject::create([
            'user_id' => $request->user()?->id ?? User::first()->id,
            'title' => $validated['title'],
            'research_question' => $validated['research_question'],
            'topic' => $validated['topic'] ?? null,
            'entity_id' => $validated['entity_id'] ?? null,
            'depth' => $validated['depth'] ?? 'standard',
            'output_format' => $validated['output_format'] ?? 'executive_summary',
            'time_range_start' => $validated['time_range_start'] ?? null,
            'time_range_end' => $validated['time_range_end'] ?? null,
            'geographic_region' => $validated['geographic_region'] ?? null,
            'status' => 'queued',
            'progress_percent' => 10,
        ]);

        // Execute research asynchronously or synchronously
        $report = $this->researchService->executeResearch($project);

        return response()->json([
            'success' => true,
            'message' => 'Deep research executed and report generated.',
            'data' => [
                'project' => $project->fresh(),
                'report' => $report,
            ],
        ], 201);
    }

    /**
     * Research Reports Library
     */
    public function listReports(Request $request): JsonResponse
    {
        $query = ResearchReport::with(['project', 'reviewer'])->withCount('citations')->latest();

        if ($request->filled('status')) {
            $query->where('editorial_status', $request->query('status'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->integer('per_page', 15)),
        ]);
    }

    /**
     * Show Report
     */
    public function showReport(string $id): JsonResponse
    {
        $report = ResearchReport::with(['project.entity', 'citations.source', 'reviewer'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Update Report Status
     */
    public function updateReportStatus(Request $request, string $id): JsonResponse
    {
        $report = ResearchReport::findOrFail($id);

        $validated = $request->validate([
            'editorial_status' => 'required|in:draft,in_review,editorial_approved,archived',
            'is_public' => 'nullable|boolean',
        ]);

        $report->update([
            'editorial_status' => $validated['editorial_status'],
            'is_public' => $validated['is_public'] ?? $report->is_public,
            'reviewer_id' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report editorial status updated.',
            'data' => $report->fresh(),
        ]);
    }
}
