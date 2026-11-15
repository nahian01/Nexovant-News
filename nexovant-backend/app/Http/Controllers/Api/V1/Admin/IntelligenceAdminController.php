<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Article;
use App\Models\AuditLog;
use App\Models\DiscoveredStory;
use App\Models\ExtractedFact;
use App\Models\NewsSource;
use App\Models\PipelineJob;
use App\Models\StoryCluster;
use App\Services\Ingestion\EvidenceVerificationService;
use App\Services\Ingestion\IntelligencePipelineCoordinator;
use App\Services\Ingestion\StoryClusteringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Throwable;

class IntelligenceAdminController
{
    public function __construct(
        protected IntelligencePipelineCoordinator $coordinator,
        protected StoryClusteringService $clusteringService,
        protected EvidenceVerificationService $verificationService
    ) {
    }

    /**
     * Ingestion Dashboard: Aggregated intelligence telemetry and recent discoveries.
     */
    public function dashboard(): JsonResponse
    {
        $today = now()->startOfDay();

        $stats = [
            'total_sources' => NewsSource::count(),
            'active_sources' => NewsSource::where('is_active', true)->count(),
            'healthy_sources' => NewsSource::where('health_status', 'healthy')->count(),
            'failed_sources' => NewsSource::where('health_status', 'failed')->count(),
            'stories_discovered_today' => DiscoveredStory::where('created_at', '>=', $today)->count(),
            'stories_processed_today' => DiscoveredStory::where('status', '!=', 'discovered')->where('created_at', '>=', $today)->count(),
            'clusters_created' => StoryCluster::count(),
            'facts_extracted' => ExtractedFact::count(),
            'articles_generated' => Article::count(),
            'articles_awaiting_review' => Article::where('status', 'REVIEW')->count(),
            'articles_published_auto' => Article::where('status', 'PUBLISHED')->count(),
            'failed_pipeline_jobs' => PipelineJob::where('status', 'failed')->count(),
        ];

        $recentDiscoveries = DiscoveredStory::with('source:id,name,credibility_score')
            ->latest('created_at')
            ->limit(10)
            ->get();

        $recentErrors = PipelineJob::where('status', 'failed')
            ->latest('created_at')
            ->limit(10)
            ->get();

        $recentJobs = PipelineJob::latest('created_at')
            ->limit(10)
            ->get();

        $settings = $this->loadAutomationSettings();
        $metrics = [
            'total_discovered' => DiscoveredStory::count(),
            'pending_processing' => DiscoveredStory::where('processing_status', 'pending')->count(),
            'deduplicated_count' => DiscoveredStory::where('is_duplicate', true)->count(),
            'active_clusters' => StoryCluster::count(),
            'verified_facts' => ExtractedFact::where('verification_status', 'supported_multiple')->count(),
            'articles_generated' => Article::count(),
            'active_sources' => NewsSource::where('is_active', true)->count(),
        ];

        $pipelineSummary = [
            'health_status' => 'HEALTHY',
            'publication_mode' => $settings['publishing_mode'] ?? 'AUTO_REVIEW',
            'is_paused' => (bool) ($settings['is_ingestion_paused'] ?? false),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'metrics' => $metrics,
                'pipeline_summary' => $pipelineSummary,
                'recent_discoveries' => $recentDiscoveries,
                'recent_pipeline_jobs' => $recentJobs,
            ],
            'stats' => $stats,
            'metrics' => $metrics,
            'recent_discoveries' => $recentDiscoveries,
            'recent_errors' => $recentErrors,
            'publication_mode' => $settings['publishing_mode'] ?? 'AUTO_REVIEW',
            'automation_paused' => (bool) ($settings['is_ingestion_paused'] ?? false),
        ]);
    }

    /**
     * Ingestion Control: View automation status and trigger on-demand feed scans.
     */
    public function ingestion(): JsonResponse
    {
        $sources = NewsSource::orderBy('last_polled_at', 'desc')->get();
        $settings = $this->loadAutomationSettings();
        $isPaused = (bool) ($settings['is_ingestion_paused'] ?? false);

        return response()->json([
            'success' => true,
            'data' => [
                'sources' => $sources,
                'is_paused' => $isPaused,
                'automation_paused' => $isPaused,
            ],
            'automation_paused' => $isPaused,
            'sources' => $sources,
            'last_run' => PipelineJob::latest('created_at')->first()?->created_at?->toIso8601String(),
        ]);
    }

    /**
     * Trigger ingestion scan for selected source or all eligible sources.
     */
    public function triggerIngestion(Request $request): JsonResponse
    {
        $admin = $request->user();
        $sourceId = $request->input('source_id');

        $query = NewsSource::where('is_active', true);
        if ($sourceId) {
            $query->where('id', $sourceId);
        }

        $sources = $query->get();
        $results = [];

        foreach ($sources as $source) {
            try {
                $res = $this->coordinator->processSource($source, $admin);
                $results[] = [
                    'source_name' => $source->name,
                    'status' => 'success',
                    'stats' => $res,
                ];
            } catch (Throwable $e) {
                $results[] = [
                    'source_name' => $source->name,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        AuditLog::create([
            'user_id' => $admin?->id,
            'action' => 'pipeline.ingestion_triggered',
            'resource_type' => 'PipelineJob',
            'new_values' => ['sources_count' => count($sources), 'source_id' => $sourceId],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Ingestion completed for " . count($sources) . " source(s).",
            'results' => $results,
        ]);
    }

    /**
     * Pause or resume automated ingestion runs.
     */
    public function toggleAutomation(Request $request): JsonResponse
    {
        $admin = $request->user();
        $settings = $this->loadAutomationSettings();

        $requestedPaused = $request->has('is_paused')
            ? (bool) $request->input('is_paused')
            : !(bool) ($settings['is_ingestion_paused'] ?? false);

        $settings['is_ingestion_paused'] = $requestedPaused;
        $settings['pipeline_automation_paused'] = $requestedPaused;
        $this->saveAutomationSettings($settings);

        AuditLog::create([
            'user_id' => $admin?->id,
            'action' => 'pipeline.automation_toggled',
            'resource_type' => 'AutomationConfig',
            'new_values' => ['paused' => $requestedPaused],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Pipeline automation is now " . ($requestedPaused ? 'PAUSED' : 'ACTIVE') . '.',
            'is_paused' => $requestedPaused,
            'automation_paused' => $requestedPaused,
        ]);
    }

    /**
     * Discovery Workspace: List discovered stories with filtering and duplicate status.
     */
    public function discoveries(Request $request): JsonResponse
    {
        $query = DiscoveredStory::with(['source:id,name,credibility_score', 'cluster:id,title'])
            ->latest('published_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($sourceId = $request->query('source_id')) {
            $query->where('source_id', $sourceId);
        }

        if ($search = $request->query('q')) {
            $query->where('title', 'like', "%{$search}%");
        }

        $stories = $query->paginate((int) $request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $stories->items(),
            'meta' => [
                'current_page' => $stories->currentPage(),
                'last_page' => $stories->lastPage(),
                'total' => $stories->total(),
            ]
        ]);
    }

    /**
     * Show single discovered story details.
     */
    public function showDiscovery(string $id): JsonResponse
    {
        $story = DiscoveredStory::with([
            'source',
            'cluster.stories',
            'facts',
            'duplicateOf:id,title,canonical_url',
            'duplicates:id,title,canonical_url,created_at',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $story,
        ]);
    }

    /**
     * Assign a story to a cluster manually.
     */
    public function assignStoryToCluster(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cluster_id' => ['required', 'exists:story_clusters,id'],
        ]);

        $story = DiscoveredStory::findOrFail($id);
        $cluster = StoryCluster::findOrFail($validated['cluster_id']);

        $this->clusteringService->addStoryToCluster($cluster, $story, 1.0);

        return response()->json([
            'success' => true,
            'message' => "Story '{$story->title}' assigned to cluster '{$cluster->title}'.",
            'cluster' => $cluster->load('stories'),
        ]);
    }

    /**
     * Mark a false duplicate as valid and restore to workflow.
     */
    public function markFalseDuplicate(string $id, Request $request): JsonResponse
    {
        $story = DiscoveredStory::findOrFail($id);
        $this->clusteringService->markFalseDuplicate($story, $request->user());

        return response()->json([
            'success' => true,
            'message' => "False duplicate resolved. Story '{$story->title}' restored to discovery pipeline.",
            'story' => $story,
        ]);
    }

    /**
     * Exclude a story from further processing.
     */
    public function excludeStory(string $id): JsonResponse
    {
        $story = DiscoveredStory::findOrFail($id);
        $story->update(['status' => 'excluded']);

        return response()->json([
            'success' => true,
            'message' => "Story '{$story->title}' excluded from processing.",
        ]);
    }

    /**
     * Story Clustering: List active clusters with similarity and source counts.
     */
    public function clusters(Request $request): JsonResponse
    {
        $clusters = StoryCluster::with([
            'stories.source:id,name,credibility_score',
            'article:id,title,status,slug',
        ])
        ->where('status', '!=', 'archived')
        ->latest('created_at')
        ->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $clusters->items(),
            'meta' => [
                'current_page' => $clusters->currentPage(),
                'last_page' => $clusters->lastPage(),
                'total' => $clusters->total(),
            ]
        ]);
    }

    /**
     * Show cluster detail with full source breakdown and extracted facts.
     */
    public function showCluster(string $id): JsonResponse
    {
        $cluster = StoryCluster::with([
            'stories.source',
            'facts.source',
            'article.summary',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $cluster,
        ]);
    }

    /**
     * Merge two clusters with reason.
     */
    public function mergeClusters(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'primary_cluster_id' => ['required', 'exists:story_clusters,id'],
            'secondary_cluster_id' => ['required', 'exists:story_clusters,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $primary = StoryCluster::findOrFail($validated['primary_cluster_id']);
        $secondary = StoryCluster::findOrFail($validated['secondary_cluster_id']);

        $merged = $this->clusteringService->mergeClusters($primary, $secondary, $validated['reason'] ?? null, $request->user());

        return response()->json([
            'success' => true,
            'message' => "Story clusters merged successfully into '{$merged->title}'.",
            'cluster' => $merged->load('stories'),
        ]);
    }

    /**
     * Split a cluster.
     */
    public function splitCluster(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cluster_id' => ['required', 'exists:story_clusters,id'],
            'story_ids' => ['required', 'array', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $cluster = StoryCluster::findOrFail($validated['cluster_id']);
        $newCluster = $this->clusteringService->splitCluster(
            cluster: $cluster,
            storyIdsToDetach: $validated['story_ids'],
            reason: $validated['reason'] ?? null,
            admin: $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => "Cluster split successfully into new cluster '{$newCluster->title}'.",
            'new_cluster' => $newCluster->load('stories'),
        ]);
    }

    /**
     * Fact Verification Workspace: List facts with status filters.
     */
    public function facts(Request $request): JsonResponse
    {
        $query = ExtractedFact::with(['source:id,name,credibility_score', 'cluster:id,title'])
            ->latest('created_at');

        if ($status = $request->query('status')) {
            $query->where('verification_status', $status);
        }

        if ($clusterId = $request->query('cluster_id')) {
            $query->where('cluster_id', $clusterId);
        }

        if ($search = $request->query('q')) {
            $query->where('claim', 'like', "%{$search}%");
        }

        $facts = $query->paginate((int) $request->query('per_page', 30));

        return response()->json([
            'success' => true,
            'data' => $facts->items(),
            'meta' => [
                'current_page' => $facts->currentPage(),
                'last_page' => $facts->lastPage(),
                'total' => $facts->total(),
            ]
        ]);
    }

    /**
     * Update verification status of a fact.
     */
    public function updateFactStatus(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:supported_multiple,supported_single,conflicting,unverified,contradicted'],
            'editorial_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $fact = ExtractedFact::findOrFail($id);
        $updated = $this->verificationService->updateFactStatus(
            fact: $fact,
            status: $validated['status'],
            notes: $validated['editorial_notes'] ?? null,
            reviewer: $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => "Fact status updated to '{$updated->verification_status}'.",
            'fact' => $updated,
        ]);
    }

    /**
     * Pipeline Monitor: List pipeline execution jobs and stage telemetry.
     */
    public function pipeline(Request $request): JsonResponse
    {
        $query = PipelineJob::with(['source:id,name', 'provider:id,name', 'model:id,display_name'])
            ->latest('created_at');

        if ($stage = $request->query('stage')) {
            $query->where('stage', $stage);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $jobs = $query->paginate((int) $request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $jobs->items(),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'total' => $jobs->total(),
            ]
        ]);
    }

    /**
     * Retry a failed pipeline job or stage.
     */
    public function retryPipelineJob(string $id, Request $request): JsonResponse
    {
        $job = PipelineJob::findOrFail($id);
        $job->update([
            'status' => 'processing',
            'error_message' => null,
            'started_at' => now(),
            'completed_at' => null,
        ]);

        // If source associated, re-process
        if ($job->source_id) {
            $source = NewsSource::find($job->source_id);
            if ($source) {
                try {
                    $this->coordinator->processSource($source, $request->user());
                } catch (Throwable $e) {
                    // Logged in job
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Pipeline job '{$job->id}' successfully restarted.",
            'job' => $job->fresh(),
        ]);
    }

    /**
     * Cancel an active pipeline job.
     */
    public function cancelPipelineJob(string $id): JsonResponse
    {
        $job = PipelineJob::findOrFail($id);
        $job->update(['status' => 'cancelled', 'completed_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "Pipeline job marked as cancelled.",
            'job' => $job,
        ]);
    }

    /**
     * Get or update editorial automation settings.
     */
    public function automationSettings(Request $request): JsonResponse
    {
        $currentSettings = $this->loadAutomationSettings();

        if ($request->isMethod('put')) {
            $validated = $request->validate([
                'publishing_mode' => ['nullable', 'string', 'in:FULL_AUTO,AUTO_REVIEW,MANUAL_ONLY,full_auto,auto_review,manual_only'],
                'editorial_publication_mode' => ['nullable', 'string', 'in:FULL_AUTO,AUTO_REVIEW,MANUAL_ONLY,full_auto,auto_review,manual_only'],
                'min_source_credibility' => ['nullable', 'numeric', 'min:0', 'max:1'],
                'min_credibility_threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
                'min_fact_consensus_sources' => ['nullable', 'integer', 'min:1'],
                'feed_fetch_interval_minutes' => ['nullable', 'integer', 'min:5'],
                'feed_timeout_seconds' => ['nullable', 'integer', 'min:5'],
                'auto_clustering_threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
                'daily_article_limit' => ['nullable', 'integer', 'min:1'],
                'pipeline_automation_paused' => ['nullable', 'boolean'],
                'is_ingestion_paused' => ['nullable', 'boolean'],
            ]);

            // Normalize publishing_mode / editorial_publication_mode
            if (!empty($validated['publishing_mode'])) {
                $validated['publishing_mode'] = strtoupper($validated['publishing_mode']);
                $validated['editorial_publication_mode'] = strtolower($validated['publishing_mode']);
            } elseif (!empty($validated['editorial_publication_mode'])) {
                $validated['publishing_mode'] = strtoupper($validated['editorial_publication_mode']);
                $validated['editorial_publication_mode'] = strtolower($validated['editorial_publication_mode']);
            }

            if (isset($validated['min_source_credibility'])) {
                $validated['min_credibility_threshold'] = (float) $validated['min_source_credibility'];
            } elseif (isset($validated['min_credibility_threshold'])) {
                $validated['min_source_credibility'] = (float) $validated['min_credibility_threshold'];
            }

            if (isset($validated['is_ingestion_paused'])) {
                $validated['pipeline_automation_paused'] = (bool) $validated['is_ingestion_paused'];
            } elseif (isset($validated['pipeline_automation_paused'])) {
                $validated['is_ingestion_paused'] = (bool) $validated['pipeline_automation_paused'];
            }

            $merged = array_merge($currentSettings, $validated);
            $this->saveAutomationSettings($merged);

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'pipeline.settings_updated',
                'resource_type' => 'AutomationConfig',
                'new_values' => $validated,
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Editorial automation settings updated successfully.',
                'data' => $merged,
                'settings' => $merged,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $currentSettings,
            'settings' => $currentSettings,
        ]);
    }

    protected function loadAutomationSettings(): array
    {
        $path = storage_path('app/settings.json');
        $defaults = [
            'publishing_mode' => 'AUTO_REVIEW',
            'editorial_publication_mode' => 'auto_review',
            'min_source_credibility' => 0.70,
            'min_credibility_threshold' => 0.70,
            'min_fact_consensus_sources' => 2,
            'feed_fetch_interval_minutes' => 15,
            'feed_timeout_seconds' => 15,
            'auto_clustering_threshold' => 0.45,
            'daily_article_limit' => 50,
            'pipeline_automation_paused' => false,
            'is_ingestion_paused' => false,
        ];

        if (!File::exists($path)) {
            return $defaults;
        }

        try {
            $contents = json_decode(File::get($path), true);
            return is_array($contents) ? array_merge($defaults, $contents) : $defaults;
        } catch (Throwable) {
            return $defaults;
        }
    }

    protected function saveAutomationSettings(array $settings): void
    {
        $path = storage_path('app/settings.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
