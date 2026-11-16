<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AuditLog;
use App\Models\NewsSource;
use App\Models\PipelineJob;
use App\Services\Ingestion\Connectors\SourceConnectorManager;
use App\Services\SourceNetwork\CoverageGapDetectionService;
use App\Services\SourceNetwork\GeographicCoverageService;
use App\Services\SourceNetwork\SourceDiversityService;
use App\Services\SourceNetwork\SourceHealthService;
use App\Services\SourceNetwork\SourceOnboardingService;
use App\Services\SourceNetwork\SourceReliabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

class SourceAdminController
{
    public function __construct(
        protected SourceOnboardingService $onboardingService,
        protected SourceHealthService $healthService,
        protected SourceReliabilityService $reliabilityService,
        protected SourceDiversityService $diversityService,
        protected GeographicCoverageService $coverageService,
        protected CoverageGapDetectionService $gapService,
        protected SourceConnectorManager $connectorManager
    ) {}

    /**
     * List all news sources with filters, health metrics, and tenant protection.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = NewsSource::withCount('articles');

        // Multi-tenant isolation for private sources
        if ($user && $user->organization_id) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('organization_id')
                  ->orWhere('organization_id', $user->organization_id);
            });
        } elseif ($request->filled('organization_id')) {
            $query->where('organization_id', $request->input('organization_id'));
        }

        // Filters
        if ($request->filled('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }

        if ($request->filled('editorial_status')) {
            $query->where('editorial_status', $request->input('editorial_status'));
        }

        if ($request->filled('health_status')) {
            $query->where('health_status', $request->input('health_status'));
        }

        if ($request->filled('country_code')) {
            $query->where('country_code', strtoupper($request->input('country_code')));
        }

        if ($request->filled('continent')) {
            $query->where('continent', $request->input('continent'));
        }

        if ($request->filled('language')) {
            $query->where('language', strtolower($request->input('language')));
        }

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('publisher_name', 'like', "%{$term}%")
                  ->orWhere('canonical_domain', 'like', "%{$term}%");
            });
        }

        $sources = $query->orderBy('credibility_score', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn (NewsSource $s) => $this->transformSource($s));

        return response()->json([
            'success' => true,
            'data' => $sources,
            'total' => $sources->count(),
        ]);
    }

    /**
     * Get sources currently awaiting editorial review.
     */
    public function onboardingQueue(Request $request): JsonResponse
    {
        $queue = NewsSource::whereIn('editorial_status', ['pending_review', 'discovered'])
            ->withCount('articles')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (NewsSource $s) => $this->transformSource($s));

        return response()->json([
            'success' => true,
            'data' => $queue,
            'count' => $queue->count(),
        ]);
    }

    /**
     * Onboard a new source with SSRF verification and security checks.
     */
    public function onboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'publisher_name' => ['nullable', 'string', 'max:255'],
            'source_type' => ['required', 'string'],
            'website_url' => ['nullable', 'url'],
            'feed_url' => ['nullable', 'url'],
            'api_endpoint' => ['nullable', 'url'],
            'country_code' => ['nullable', 'string', 'max:4'],
            'continent' => ['nullable', 'string', 'max:64'],
            'language' => ['nullable', 'string', 'max:10'],
            'supported_languages' => ['nullable', 'array'],
            'publication_frequency' => ['nullable', 'string'],
            'auth_type' => ['nullable', 'string', 'in:none,bearer,api_key,basic'],
            'credentials' => ['nullable', 'array'],
            'attribution_requirements' => ['nullable', 'string', 'max:500'],
            'terms_of_use_url' => ['nullable', 'url'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1'],
            'organization_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'onboarding_notes' => ['nullable', 'string'],
            'credibility_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $user = $request->user();

        // Enforce organization restriction if non-superadmin
        if ($user && $user->organization_id) {
            $validated['organization_id'] = $user->organization_id;
        }

        try {
            $source = $this->onboardingService->submitSource($validated, $user);

            AuditLog::create([
                'user_id' => $user?->id ?? 1,
                'action' => 'source.onboarded',
                'resource_type' => 'NewsSource',
                'resource_id' => $source->id,
                'new_values' => ['name' => $source->name, 'source_type' => $source->source_type],
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Source '{$source->name}' successfully onboarded.",
                'data' => $this->transformSource($source),
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => "Onboarding failed: " . $e->getMessage(),
                'error' => [
                    'code' => 'ONBOARDING_FAILED',
                    'detail' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Approve a source in the onboarding queue.
     */
    public function approve(string $id, Request $request): JsonResponse
    {
        $source = NewsSource::findOrFail($id);
        $this->authorizeSourceAccess($request->user(), $source);

        $notes = $request->input('notes');
        $approvedSource = $this->onboardingService->approveSource($source, $notes, $request->user());

        AuditLog::create([
            'user_id' => $request->user()?->id ?? 1,
            'action' => 'source.approved',
            'resource_type' => 'NewsSource',
            'resource_id' => $source->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Source '{$approvedSource->name}' has been approved and activated.",
            'data' => $this->transformSource($approvedSource),
        ]);
    }

    /**
     * Reject a source in the onboarding queue.
     */
    public function reject(string $id, Request $request): JsonResponse
    {
        $source = NewsSource::findOrFail($id);
        $this->authorizeSourceAccess($request->user(), $source);

        $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $reason = $request->input('reason');

        $rejectedSource = $this->onboardingService->rejectSource($source, $reason, $request->user());

        return response()->json([
            'success' => true,
            'message' => "Source '{$rejectedSource->name}' was rejected.",
            'data' => $this->transformSource($rejectedSource),
        ]);
    }

    /**
     * Suspend a source.
     */
    public function suspend(string $id, Request $request): JsonResponse
    {
        $source = NewsSource::findOrFail($id);
        $this->authorizeSourceAccess($request->user(), $source);

        $reason = $request->input('reason', 'Administrative suspension');
        $suspended = $this->onboardingService->suspendSource($source, $reason);

        return response()->json([
            'success' => true,
            'message' => "Source '{$suspended->name}' suspended.",
            'data' => $this->transformSource($suspended),
        ]);
    }

    /**
     * Test connection to a collection source with sample item extraction and latency measurement.
     */
    public function testConnection(string $id, Request $request): JsonResponse
    {
        $source = NewsSource::findOrFail($id);
        $this->authorizeSourceAccess($request->user(), $source);

        try {
            $result = $this->connectorManager->testSource($source);

            // Record health log
            $this->healthService->checkSourceHealth($source);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'latency_ms' => $result['latency_ms'],
                'status' => $result['status'],
                'http_status' => $result['http_status'],
                'sample_items' => $result['sample_items'],
                'attribution' => $result['attribution'],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => "Connection test failed: " . $e->getMessage(),
                'status' => 'unavailable',
                'latency_ms' => 0,
            ], 422);
        }
    }

    /**
     * Get platform-wide health diagnostics.
     */
    public function healthOverview(Request $request): JsonResponse
    {
        $overview = $this->healthService->getHealthOverview();
        return response()->json([
            'success' => true,
            'data' => $overview,
        ]);
    }

    /**
     * Trigger health checks across network.
     */
    public function triggerHealthCheck(Request $request): JsonResponse
    {
        $sourceId = $request->input('source_id');
        if ($sourceId) {
            $source = NewsSource::findOrFail($sourceId);
            $this->authorizeSourceAccess($request->user(), $source);
            $log = $this->healthService->checkSourceHealth($source);
            return response()->json([
                'success' => true,
                'data' => $log,
                'message' => "Health check executed for {$source->name}: status {$log->status}.",
            ]);
        }

        $type = $request->input('source_type');
        $results = $this->healthService->checkAllSources($type);

        return response()->json([
            'success' => true,
            'data' => $results,
            'message' => "Health check completed across {$results['total_checked']} sources.",
        ]);
    }

    /**
     * Get geographic coverage analytics.
     */
    public function coverageAnalytics(Request $request): JsonResponse
    {
        $breakdown = $this->coverageService->getCoverageBreakdown();
        return response()->json([
            'success' => true,
            'data' => $breakdown,
        ]);
    }

    /**
     * Get coverage gap analysis and recommendations.
     */
    public function coverageGaps(Request $request): JsonResponse
    {
        $gaps = $this->gapService->detectGaps();
        return response()->json([
            'success' => true,
            'data' => $gaps,
        ]);
    }

    /**
     * Get source diversity and independence metrics.
     */
    public function diversityMetrics(Request $request): JsonResponse
    {
        $metrics = $this->diversityService->getSystemDiversityMetrics();
        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * Get source reliability profile.
     */
    public function reliabilityProfile(string $id, Request $request): JsonResponse
    {
        $source = NewsSource::findOrFail($id);
        $this->authorizeSourceAccess($request->user(), $source);

        $profile = $this->reliabilityService->calculateReliabilityProfile($source);

        return response()->json([
            'success' => true,
            'source_id' => $source->id,
            'source_name' => $source->name,
            'reliability_profile' => $profile,
        ]);
    }

    /**
     * Legacy store method (compatible with Stage 1-13 tests).
     */
    public function store(Request $request): JsonResponse
    {
        return $this->onboard($request);
    }

    /**
     * Update an existing collection source.
     */
    public function update(string $id, Request $request): JsonResponse
    {
        $source = NewsSource::findOrFail($id);
        $this->authorizeSourceAccess($request->user(), $source);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'publisher_name' => ['nullable', 'string', 'max:255'],
            'source_type' => ['sometimes', 'string'],
            'website_url' => ['nullable', 'url'],
            'feed_url' => ['nullable', 'url'],
            'api_endpoint' => ['nullable', 'url'],
            'credibility_score' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'rate_limit_per_minute' => ['sometimes', 'integer', 'min:1'],
            'fetch_interval_minutes' => ['sometimes', 'integer', 'min:1'],
            'country_code' => ['nullable', 'string', 'max:4'],
            'continent' => ['nullable', 'string', 'max:64'],
            'language' => ['nullable', 'string', 'max:10'],
            'supported_languages' => ['nullable', 'array'],
            'attribution_requirements' => ['nullable', 'string', 'max:500'],
            'terms_of_use_url' => ['nullable', 'url'],
            'is_active' => ['sometimes', 'boolean'],
            'editorial_status' => ['sometimes', 'string'],
            'credentials' => ['nullable', 'array'],
        ]);

        if (isset($validated['credentials'])) {
            $source->setEncryptedCredentialsAttribute($validated['credentials']);
            unset($validated['credentials']);
        }

        $source->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Source '{$source->name}' updated successfully.",
            'data' => $this->transformSource($source),
        ]);
    }

    /**
     * Trigger immediate ingestion scan for a single source.
     */
    public function scan(string $id, Request $request): JsonResponse
    {
        $source = NewsSource::findOrFail($id);
        $this->authorizeSourceAccess($request->user(), $source);
        $admin = $request->user();

        $job = PipelineJob::create([
            'source_id' => $source->id,
            'stage' => '1. Source Discovery',
            'status' => 'processing',
            'item_title' => "Scan for {$source->name}",
            'started_at' => now(),
        ]);

        $source->update([
            'last_polled_at' => now(),
            'total_items_ingested' => $source->total_items_ingested + 1,
        ]);

        AuditLog::create([
            'user_id' => $admin?->id ?? 1,
            'action' => 'source.scan_triggered',
            'resource_type' => 'NewsSource',
            'resource_id' => $source->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Ingestion scan initiated for '{$source->name}'.",
            'job' => $job,
        ]);
    }

    /**
     * Toggle source active/inactive state.
     */
    public function toggle(string $id, Request $request): JsonResponse
    {
        $source = NewsSource::findOrFail($id);
        $this->authorizeSourceAccess($request->user(), $source);

        $source->is_active = !$source->is_active;
        $source->health_status = $source->is_active ? 'healthy' : 'paused';
        $source->save();

        return response()->json([
            'success' => true,
            'message' => "Source '{$source->name}' is now " . ($source->is_active ? 'active' : 'paused') . '.',
            'is_active' => $source->is_active,
            'health_status' => $source->health_status,
        ]);
    }

    /**
     * Delete a news source.
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        $source = NewsSource::findOrFail($id);
        $this->authorizeSourceAccess($request->user(), $source);

        $name = $source->name;
        $source->delete();

        return response()->json([
            'success' => true,
            'message' => "Source '{$name}' deleted.",
        ]);
    }

    /**
     * Transform source for safe API response (mask credentials).
     */
    protected function transformSource(NewsSource $source): array
    {
        $data = $source->toArray();
        unset($data['encrypted_credentials']);
        $data['has_credentials'] = $source->hasCredentials();
        return $data;
    }

    /**
     * Ensure current user is authorized to access the source.
     */
    protected function authorizeSourceAccess(?\App\Models\User $user, NewsSource $source): void
    {
        if (!$user) {
            return;
        }

        // If source is owned by an organization, require membership
        if ($source->organization_id !== null) {
            $userOrgId = $user->organization_id;
            if ($userOrgId !== null && (string) $userOrgId !== (string) $source->organization_id) {
                abort(403, "Access to private organization intelligence source is forbidden.");
            }
            if ($userOrgId === null && $user->account_type !== 'admin') {
                abort(403, "Access to private organization intelligence source is forbidden.");
            }
        }
    }
}
