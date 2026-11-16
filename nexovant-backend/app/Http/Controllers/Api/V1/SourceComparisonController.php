<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\Discovery\SourceCorroborationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SourceComparisonController
{
    public function __construct(
        protected SourceCorroborationService $corroborationService
    ) {}

    /**
     * Get corroboration breakdown for an article or story cluster.
     */
    public function corroboration(Request $request, string $id): JsonResponse
    {
        $type = (string) $request->input('type', 'article');
        $report = $this->corroborationService->getCorroborationReport($id, $type);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Side-by-side comparison of multiple articles covering the same event.
     */
    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'article_ids' => ['required', 'array', 'min:2', 'max:5'],
            'article_ids.*' => ['required', 'string'],
        ]);

        $comparison = $this->corroborationService->getSourceComparison(
            (array) $request->input('article_ids')
        );

        return response()->json([
            'success' => true,
            'data' => $comparison,
        ]);
    }
}
