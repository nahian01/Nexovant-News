<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Automation;

use App\Models\AutomatedIntelligenceReport;
use App\Models\OrganizationMember;
use App\Services\Automation\AutomatedIntelligenceReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AutomatedReportController
{
    public function __construct(
        protected AutomatedIntelligenceReportService $reportService
    ) {}

    /**
     * List automated intelligence reports.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = null;

        if ($user) {
            $membership = OrganizationMember::where('user_id', $user->id)->first();
            $tenantId = $membership?->organization_id ?? $user->organization_id;
        }

        $query = AutomatedIntelligenceReport::latest('published_at');

        if ($tenantId !== null) {
            $query->where(function ($q) use ($tenantId) {
                $q->whereNull('organization_id')->orWhere('organization_id', $tenantId);
            });
        } else {
            $query->whereNull('organization_id');
        }

        if ($request->filled('type') && $request->input('type') !== 'all') {
            $query->where('report_type', $request->input('type'));
        }

        $reports = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $reports->items(),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * View full report.
     */
    public function show(Request $request, string $slugOrId): JsonResponse
    {
        $user = $request->user();
        $tenantId = null;

        if ($user) {
            $membership = OrganizationMember::where('user_id', $user->id)->first();
            $tenantId = $membership?->organization_id ?? $user->organization_id;
        }

        $report = AutomatedIntelligenceReport::where('id', $slugOrId)
            ->orWhere('slug', $slugOrId)
            ->firstOrFail();

        if ($report->organization_id !== null && $report->organization_id !== $tenantId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to organization report.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Trigger on-demand report generation.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'report_type' => 'required|string|in:daily_summary,weekly_summary,weekly_intelligence,emerging_trend,early_warning_dossier,ad_hoc_brief',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $user = $request->user();
        $tenantId = null;

        if ($user) {
            $membership = OrganizationMember::where('user_id', $user->id)->first();
            $tenantId = $membership?->organization_id ?? $user->organization_id;
        }

        $startDate = $request->filled('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate = $request->filled('end_date') ? Carbon::parse($request->input('end_date')) : null;

        $report = $this->reportService->generateReport(
            reportType: (string) $request->input('report_type'),
            startDate: $startDate,
            endDate: $endDate,
            user: $user,
            tenantId: $tenantId
        );

        return response()->json([
            'success' => true,
            'message' => 'Executive intelligence report synthesized successfully.',
            'report' => $report,
            'data' => $report,
        ]);
    }

    /**
     * Export report to Markdown or JSON.
     */
    public function export(Request $request, string $id): Response
    {
        $user = $request->user();
        $tenantId = null;

        if ($user) {
            $membership = OrganizationMember::where('user_id', $user->id)->first();
            $tenantId = $membership?->organization_id ?? $user->organization_id;
        }

        $report = AutomatedIntelligenceReport::findOrFail($id);

        if ($report->organization_id !== null && $report->organization_id !== $tenantId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to organization report.'], 403);
        }

        $format = $request->input('format', 'markdown');

        if ($format === 'json') {
            return response()->json($report->toArray());
        }

        $markdown = $this->reportService->exportMarkdown($report);

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown',
            'Content-Disposition' => "attachment; filename=\"{$report->slug}.md\"",
        ]);
    }
}
