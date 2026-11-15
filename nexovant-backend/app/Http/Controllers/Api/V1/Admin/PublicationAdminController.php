<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Publication;
use App\Models\PublicationDownloadLog;
use App\Models\PublicationDownloadToken;
use App\Models\PublicationRevision;
use App\Models\PublicationSchedule;
use App\Models\PublicationTemplate;
use App\Services\Publication\PdfRenderingService;
use App\Services\Publication\PublicationDistributionService;
use App\Services\Publication\PublicationGenerationService;
use App\Services\Publication\PublicationScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicationAdminController
{
    public function __construct(
        protected PublicationGenerationService $generator,
        protected PdfRenderingService $pdfRenderer,
        protected PublicationDistributionService $distributor,
        protected PublicationScheduleService $scheduler
    ) {}

    /**
     * Executive Publication Control Center Overview.
     */
    public function overview(): JsonResponse
    {
        $total = Publication::count();
        $published = Publication::where('status', 'published')->count();
        $pending = Publication::where('status', 'pending_review')->count();
        $drafts = Publication::where('status', 'draft')->count();
        $schedules = PublicationSchedule::where('is_enabled', true)->count();
        $downloads = PublicationDownloadLog::count();

        $recentDownloads = PublicationDownloadLog::with(['publication:id,title,edition_number', 'user:id,name,email'])
            ->latest('downloaded_at')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'metrics' => [
                'total_publications' => $total,
                'published_editions' => $published,
                'pending_reviews' => $pending,
                'drafts' => $drafts,
                'active_schedules' => $schedules,
                'total_downloads' => $downloads,
            ],
            'recent_downloads' => $recentDownloads,
        ]);
    }

    /**
     * Publication Library list with search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Publication::with(['template:id,name,slug', 'user:id,name', 'approver:id,name']);

        if ($request->filled('type')) {
            $query->where('publication_type', $request->query('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('subtitle', 'like', "%{$search}%")
                  ->orWhere('edition_number', 'like', "%{$search}%");
            });
        }

        $publications = $query->latest('publication_date')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $publications->items(),
            'meta' => [
                'current_page' => $publications->currentPage(),
                'last_page' => $publications->lastPage(),
                'total' => $publications->total(),
            ],
        ]);
    }

    /**
     * Generate a new publication draft via PublicationGenerationService.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'publication_type' => ['required', 'string', 'in:daily_briefing,weekly_report,company_dossier,executive_dossier,technology_report,industry_report,competitive_report,historical_report,trend_report,custom_deep_research'],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'reporting_period' => ['nullable', 'string', 'max:100'],
            'template_id' => ['nullable', 'uuid', 'exists:publication_templates,id'],
            'visibility' => ['nullable', 'string', 'in:public,registered,premium,enterprise,internal'],
            'force_regenerate' => ['nullable', 'boolean'],
        ]);

        $publication = $this->generator->generate([
            'publication_type' => $validated['publication_type'],
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? null,
            'reporting_period' => $validated['reporting_period'] ?? null,
            'template_id' => $validated['template_id'] ?? null,
            'visibility' => $validated['visibility'] ?? 'premium',
            'user_id' => $request->user()?->id,
            'force_regenerate' => (bool) ($validated['force_regenerate'] ?? false),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Publication '{$publication->title}' successfully synthesized.",
            'publication' => $publication,
        ], 201);
    }

    /**
     * Show single publication with content and revisions.
     */
    public function show(string $id): JsonResponse
    {
        $publication = Publication::with(['template', 'revisions.user:id,name', 'approver:id,name'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'publication' => $publication,
        ]);
    }

    /**
     * Update publication content (editorial review edits).
     */
    public function update(string $id, Request $request): JsonResponse
    {
        $publication = Publication::findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'visibility' => ['sometimes', 'string', 'in:public,registered,premium,enterprise,internal'],
            'content_json' => ['required', 'array'],
            'change_summary' => ['nullable', 'string', 'max:255'],
        ]);

        $latestVersion = $publication->revisions()->max('version') ?? 1;
        $nextVersion = $latestVersion + 1;

        $publication->update([
            'title' => $validated['title'] ?? $publication->title,
            'subtitle' => $validated['subtitle'] ?? $publication->subtitle,
            'visibility' => $validated['visibility'] ?? $publication->visibility,
            'content_json' => $validated['content_json'],
        ]);

        // Save new revision
        PublicationRevision::create([
            'publication_id' => $publication->id,
            'user_id' => $request->user()?->id,
            'version' => $nextVersion,
            'content_json' => $validated['content_json'],
            'change_summary' => $validated['change_summary'] ?? 'Editorial content updated.',
            'created_at' => now(),
        ]);

        // Re-render PDF with updated content
        $this->pdfRenderer->render($publication);

        return response()->json([
            'success' => true,
            'message' => 'Editorial changes saved and PDF re-rendered.',
            'publication' => $publication->fresh(['revisions']),
        ]);
    }

    /**
     * Approve publication.
     */
    public function approve(string $id, Request $request): JsonResponse
    {
        $publication = Publication::findOrFail($id);
        $publication->update([
            'status' => 'approved',
            'approved_by' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Publication '{$publication->title}' marked as approved.",
            'publication' => $publication,
        ]);
    }

    /**
     * Publish publication.
     */
    public function publish(string $id, Request $request): JsonResponse
    {
        $publication = Publication::findOrFail($id);

        // Ensure PDF is generated before publishing
        if (! $publication->file_path || $publication->file_path === 'pending') {
            $this->pdfRenderer->render($publication);
        }

        $publication->update([
            'status' => 'published',
            'approved_by' => $publication->approved_by ?? $request->user()?->id,
            'published_at' => now(),
            'is_public' => ($publication->visibility === 'public'),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Publication '{$publication->title}' is now published and available to subscribers.",
            'publication' => $publication,
        ]);
    }

    /**
     * Archive publication.
     */
    public function archive(string $id): JsonResponse
    {
        $publication = Publication::findOrFail($id);
        $publication->update(['status' => 'archived']);

        return response()->json([
            'success' => true,
            'message' => "Publication '{$publication->title}' has been archived.",
            'publication' => $publication,
        ]);
    }

    /**
     * Delete publication and associated records.
     */
    public function destroy(string $id): JsonResponse
    {
        $publication = Publication::findOrFail($id);
        $publication->delete();

        return response()->json([
            'success' => true,
            'message' => 'Publication deleted successfully.',
        ]);
    }

    /**
     * Template Management: List templates.
     */
    public function templates(): JsonResponse
    {
        $templates = PublicationTemplate::withCount('publications')->get();
        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }

    /**
     * Template Management: Store template.
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'page_size' => ['required', 'string', 'in:a4,letter'],
            'orientation' => ['required', 'string', 'in:portrait,landscape'],
            'brand_config' => ['nullable', 'array'],
            'confidentiality_label' => ['required', 'string', 'max:100'],
            'disclosure_text' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $slug = Str::slug($validated['name']);
        if (PublicationTemplate::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::random(4);
        }

        $template = PublicationTemplate::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'page_size' => $validated['page_size'],
            'orientation' => $validated['orientation'],
            'brand_config' => $validated['brand_config'] ?? [
                'logo_text' => 'NEXOVANT',
                'primary_color' => '#0A0E17',
                'accent_color' => '#00E5FF',
            ],
            'confidentiality_label' => $validated['confidentiality_label'],
            'disclosure_text' => $validated['disclosure_text'] ?? null,
            'is_default' => (bool) ($validated['is_default'] ?? false),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Publication template created.',
            'template' => $template,
        ], 201);
    }

    /**
     * Schedule Management: List schedules.
     */
    public function schedules(): JsonResponse
    {
        $schedules = PublicationSchedule::with('template:id,name')->get();
        return response()->json([
            'success' => true,
            'schedules' => $schedules,
        ]);
    }

    /**
     * Schedule Management: Store schedule.
     */
    public function storeSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'publication_type' => ['required', 'string'],
            'title_pattern' => ['required', 'string', 'max:255'],
            'template_id' => ['nullable', 'uuid', 'exists:publication_templates,id'],
            'frequency' => ['required', 'string', 'in:daily,weekly'],
            'schedule_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'timezone' => ['nullable', 'string'],
            'require_review' => ['nullable', 'boolean'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $schedule = PublicationSchedule::create([
            'publication_type' => $validated['publication_type'],
            'title_pattern' => $validated['title_pattern'],
            'template_id' => $validated['template_id'] ?? null,
            'frequency' => $validated['frequency'],
            'schedule_time' => $validated['schedule_time'],
            'timezone' => $validated['timezone'] ?? 'UTC',
            'require_review' => $validated['require_review'] ?? true,
            'is_enabled' => $validated['is_enabled'] ?? true,
            'next_run_at' => now()->tomorrow()->setTimeFromTimeString($validated['schedule_time']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Publication schedule configured.',
            'schedule' => $schedule,
        ], 201);
    }

    /**
     * Trigger manual execution of a schedule.
     */
    public function triggerSchedule(string $id): JsonResponse
    {
        $pub = $this->scheduler->runSchedule($id);

        return response()->json([
            'success' => (bool) $pub,
            'message' => $pub ? "Schedule executed: publication '{$pub->title}' created." : 'Schedule execution failed.',
            'publication' => $pub,
        ]);
    }

    /**
     * Distribution: Generate download token.
     */
    public function createToken(string $id, Request $request): JsonResponse
    {
        $publication = Publication::findOrFail($id);
        $user = $request->user();

        $tokenData = $this->distributor->generateDownloadToken(
            publication: $publication,
            user: $user,
            ttlMinutes: (int) $request->input('ttl_minutes', 60),
            maxDownloads: (int) $request->input('max_downloads', 5)
        );

        return response()->json([
            'success' => true,
            'download' => $tokenData,
        ]);
    }

    /**
     * Distribution: Revoke token.
     */
    public function revokeToken(string $tokenId): JsonResponse
    {
        $revoked = $this->distributor->revokeToken($tokenId);
        return response()->json([
            'success' => $revoked,
            'message' => $revoked ? 'Download token revoked.' : 'Token not found.',
        ]);
    }
}
