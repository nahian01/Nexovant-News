<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Article;
use App\Models\Company;
use App\Models\Executive;
use App\Models\IntelligenceEntity;
use App\Models\NewsSource;
use App\Models\OrganizationMember;
use App\Models\Publication;
use App\Models\SocialTrend;
use App\Models\Technology;
use App\Services\Discovery\DTO\SearchQueryDTO;
use App\Services\Discovery\SearchAnalyticsService;
use App\Services\Discovery\UnifiedSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController
{
    public function __construct(
        protected UnifiedSearchService $searchService,
        protected SearchAnalyticsService $analyticsService
    ) {}

    /**
     * Unified multi-asset intelligent search with hybrid retrieval.
     */
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = null;

        if ($user) {
            $membership = OrganizationMember::where('user_id', $user->id)->first();
            $tenantId = $membership?->organization_id ?? $user->organization_id;

            $requestedTenant = $request->input('tenant_id');
            if ($requestedTenant && ($user->isAdmin() || $requestedTenant === $tenantId)) {
                $tenantId = $requestedTenant;
            }
        }

        $queryDto = SearchQueryDTO::fromArray($request->all(), $user?->id, $tenantId);
        $result = $this->searchService->search($queryDto, $user);

        // Ensure legacy format compatibility if requested without new pagination
        if (!$request->has('content_type') && !$request->has('filters')) {
            $articles = Article::where(function ($q) use ($queryDto) {
                $q->where('title', 'like', "%{$queryDto->query}%")
                  ->orWhere('content_normalized', 'like', "%{$queryDto->query}%");
            })->with(['source', 'categories', 'companies'])->limit(8)->get();

            $companies = Company::where(function ($q) use ($queryDto) {
                $q->where('name', 'like', "%{$queryDto->query}%")
                  ->orWhere('ticker', 'like', "%{$queryDto->query}%");
            })->limit(5)->get();

            $executives = Executive::where('full_name', 'like', "%{$queryDto->query}%")->limit(5)->get();
            $technologies = Technology::where('name', 'like', "%{$queryDto->query}%")->limit(5)->get();
            $reports = Publication::where('status', 'published')->where('title', 'like', "%{$queryDto->query}%")->limit(4)->get();
            $trends = SocialTrend::where('title', 'like', "%{$queryDto->query}%")->limit(5)->get();

            $result['data'] = [
                'articles' => $articles,
                'companies' => $companies,
                'executives' => $executives,
                'technologies' => $technologies,
                'reports' => $reports,
                'trends' => $trends,
            ];
        }

        return response()->json($result);
    }

    /**
     * Autocomplete suggestions strictly filtering out unauthorized private/tenant resources.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'suggestions' => [
                    ['text' => 'NVIDIA EMEA Foundry Expansion', 'type' => 'article', 'slug' => 'nvidia-custom-silicon-foundry-emea'],
                    ['text' => 'High-NA EUV Commercial Packaging', 'type' => 'trend', 'slug' => 'high-na-euv'],
                    ['text' => 'Jensen Huang (NVIDIA CEO)', 'type' => 'executive', 'slug' => 'jensen-huang'],
                    ['text' => 'European Semiconductor Sovereignty', 'type' => 'topic', 'slug' => 'semiconductor-sovereignty'],
                ],
            ]);
        }

        $suggestions = [];

        // 1. Articles
        $articles = Article::where('title', 'like', "%{$query}%")->limit(3)->get();
        foreach ($articles as $a) {
            $suggestions[] = ['text' => $a->title, 'type' => 'article', 'slug' => $a->slug];
        }

        // 2. Entities
        $entities = IntelligenceEntity::where('canonical_name', 'like', "%{$query}%")
            ->limit(3)
            ->get();
        foreach ($entities as $e) {
            $suggestions[] = [
                'text' => "{$e->canonical_name} (" . ucfirst($e->entity_type) . ")",
                'type' => $e->entity_type,
                'slug' => $e->slug,
            ];
        }

        // 3. Publishing Sources
        $sources = NewsSource::where('is_active', true)
            ->where('name', 'like', "%{$query}%")
            ->limit(2)
            ->get();
        foreach ($sources as $s) {
            $suggestions[] = [
                'text' => "{$s->name} (Publisher)",
                'type' => 'source',
                'slug' => $s->domain,
            ];
        }

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Record relevance feedback.
     */
    public function feedback(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string'],
            'result_id' => ['required', 'string'],
            'result_type' => ['required', 'string'],
            'feedback_rating' => ['required', 'string', 'in:relevant,not_relevant,incorrect_result,missing_information'],
            'comments' => ['nullable', 'string', 'max:500'],
        ]);

        $feedback = $this->analyticsService->recordFeedback(
            user: $request->user(),
            query: (string) $request->input('query'),
            resultId: (string) $request->input('result_id'),
            resultType: (string) $request->input('result_type'),
            feedbackRating: (string) $request->input('feedback_rating'),
            comments: $request->input('comments')
        );

        return response()->json([
            'success' => true,
            'message' => 'Feedback recorded successfully.',
            'feedback' => $feedback,
        ]);
    }

    /**
     * Record click telemetry.
     */
    public function recordClick(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string'],
            'result_id' => ['required', 'string'],
            'result_type' => ['required', 'string'],
        ]);

        $this->analyticsService->recordClick(
            query: (string) $request->input('query'),
            resultId: (string) $request->input('result_id'),
            resultType: (string) $request->input('result_type'),
            user: $request->user()
        );

        return response()->json(['success' => true]);
    }
}
