<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscriber;

use App\Models\ResearchBrief;
use App\Services\Discovery\ResearchBriefService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ResearchBriefController
{
    public function __construct(
        protected ResearchBriefService $briefService
    ) {}

    /**
     * List user's saved research briefs.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->organization_id ?? null;

        $briefs = ResearchBrief::where(function ($q) use ($user, $tenantId) {
                $q->where('user_id', $user->id);
                if ($tenantId !== null) {
                    $q->orWhere(function ($oq) use ($tenantId) {
                        $oq->where('organization_id', $tenantId)
                           ->where('is_shared_with_org', true);
                    });
                }
            })
            ->with(['collection:id,name,color'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $briefs,
        ]);
    }

    /**
     * Generate grounded 13-section research brief.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:500'],
            'collection_id' => ['nullable', 'uuid'],
            'save' => ['nullable', 'boolean'],
            'is_shared_with_org' => ['nullable', 'boolean'],
        ]);

        $result = $this->briefService->generateBrief($request->user(), $request->all());

        return response()->json($result);
    }

    /**
     * Show single research brief with strict tenant/owner authorization.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->organization_id ?? null;

        $brief = ResearchBrief::where('id', $id)
            ->where(function ($q) use ($user, $tenantId) {
                $q->where('user_id', $user->id);
                if ($tenantId !== null) {
                    $q->orWhere(function ($oq) use ($tenantId) {
                        $oq->where('organization_id', $tenantId)
                           ->where('is_shared_with_org', true);
                    });
                }
            })
            ->with(['collection:id,name,color'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $brief,
        ]);
    }

    /**
     * Export brief as Markdown or JSON.
     */
    public function export(Request $request, string $id): Response|JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->organization_id ?? null;

        $brief = ResearchBrief::where('id', $id)
            ->where(function ($q) use ($user, $tenantId) {
                $q->where('user_id', $user->id);
                if ($tenantId !== null) {
                    $q->orWhere(function ($oq) use ($tenantId) {
                        $oq->where('organization_id', $tenantId)
                           ->where('is_shared_with_org', true);
                    });
                }
            })
            ->firstOrFail();

        $format = (string) $request->input('format', 'markdown');
        $content = $this->briefService->exportBrief($brief, $format);

        if ($format === 'json') {
            return response()->json(json_decode($content, true));
        }

        return response($content, 200, [
            'Content-Type' => 'text/markdown',
            'Content-Disposition' => 'attachment; filename="research-brief-' . substr($brief->id, 0, 8) . '.md"',
        ]);
    }
}
