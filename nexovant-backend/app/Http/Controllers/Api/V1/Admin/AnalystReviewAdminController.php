<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AnalystReview;
use App\Models\IntelligenceSignal;
use App\Services\Automation\AnalystReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalystReviewAdminController
{
    public function __construct(
        protected AnalystReviewService $reviewService
    ) {}

    /**
     * List items awaiting analyst review.
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->input('status', 'pending');

        $signals = IntelligenceSignal::with(['evidences', 'earlyWarning'])
            ->when($status !== 'all', fn($q) => $q->where('review_status', $status))
            ->latest('first_observed_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $signals->items(),
            'meta' => [
                'current_page' => $signals->currentPage(),
                'last_page' => $signals->lastPage(),
                'total' => $signals->total(),
            ],
        ]);
    }

    /**
     * Submit analyst review decision.
     */
    public function submitReview(Request $request, string $signalId): JsonResponse
    {
        $request->validate([
            'decision' => 'required|string|in:accepted,dismissed,escalated,reassessed,marked_false_positive',
            'feedback_notes' => 'nullable|string|max:1000',
            'added_evidence' => 'nullable|array',
            'suggested_threshold_adjustments' => 'nullable|array',
        ]);

        $signal = IntelligenceSignal::findOrFail($signalId);
        $user = $request->user();

        $review = $this->reviewService->reviewSignal(
            signal: $signal,
            analyst: $user,
            decision: (string) $request->input('decision'),
            feedbackNotes: $request->input('feedback_notes'),
            addedEvidence: $request->input('added_evidence'),
            suggestedThresholdAdjustments: $request->input('suggested_threshold_adjustments')
        );

        return response()->json([
            'success' => true,
            'message' => 'Analyst review recorded successfully.',
            'data' => $review,
            'signal_status' => $signal->fresh()->status,
        ]);
    }

    /**
     * Get review and false-positive statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $totalReviews = AnalystReview::count();
        $falsePositives = AnalystReview::where('decision', 'marked_false_positive')->count();
        $accepted = AnalystReview::where('decision', 'accepted')->count();
        $escalated = AnalystReview::where('decision', 'escalated')->count();
        $dismissed = AnalystReview::where('decision', 'dismissed')->count();

        $fpRate = $totalReviews > 0 ? round(($falsePositives / (float) $totalReviews) * 100, 1) : 0.0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_reviews' => $totalReviews,
                'false_positive_count' => $falsePositives,
                'false_positive_rate' => $fpRate,
                'accepted_count' => $accepted,
                'escalated_count' => $escalated,
                'dismissed_count' => $dismissed,
            ],
        ]);
    }
}
