<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArticleController
{
    /**
     * List verified and developing intelligence articles with multi-dimensional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Article::with([
            'source:id,name,credibility_score',
            'categories:id,name,slug,color,industry',
            'subcategories:id,name,slug',
            'companies:id,name,ticker'
        ])
        ->where('status', 'PUBLISHED')
        ->latest('published_at');

        if ($request->filled('category') && $request->query('category') !== 'all') {
            $catSlug = $request->query('category');
            $query->where(function ($q) use ($catSlug) {
                $q->whereHas('categories', function ($cq) use ($catSlug) {
                    $cq->where('slug', $catSlug)
                      ->orWhere('name', 'like', "%{$catSlug}%");
                })->orWhereHas('subcategories', function ($sq) use ($catSlug) {
                    $sq->where('slug', $catSlug)
                      ->orWhere('name', 'like', "%{$catSlug}%");
                });
            });
        }

        if ($request->filled('industry') && $request->query('industry') !== 'all') {
            $industry = $request->query('industry');
            $query->whereHas('categories', function ($cq) use ($industry) {
                $cq->where('industry', $industry)
                   ->orWhere('name', 'like', "%{$industry}%")
                   ->orWhere('slug', 'like', "%" . Str::slug($industry) . "%");
            });
        }

        if ($request->filled('verification_status') && $request->query('verification_status') !== 'all') {
            $query->where('verification_status', $request->query('verification_status'));
        }

        if ($request->boolean('is_breaking')) {
            $query->where('is_breaking', true);
        }

        if ($request->has('company_id')) {
            $query->whereHas('companies', function ($q) use ($request) {
                $q->where('companies.id', $request->query('company_id'));
            });
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content_normalized', 'like', "%{$search}%");
            });
        }

        $perPage = min(50, max(5, (int) $request->query('per_page', 15)));
        $articles = $query->paginate($perPage);

        // Dynamically compute available verification statuses with non-zero counts for the current scope
        $statusQuery = Article::query()->where('status', 'PUBLISHED');

        if ($request->filled('category') && $request->query('category') !== 'all') {
            $catSlug = $request->query('category');
            $statusQuery->where(function ($q) use ($catSlug) {
                $q->whereHas('categories', function ($cq) use ($catSlug) {
                    $cq->where('slug', $catSlug)
                      ->orWhere('name', 'like', "%{$catSlug}%");
                })->orWhereHas('subcategories', function ($sq) use ($catSlug) {
                    $sq->where('slug', $catSlug)
                      ->orWhere('name', 'like', "%{$catSlug}%");
                });
            });
        }

        if ($request->filled('industry') && $request->query('industry') !== 'all') {
            $industry = $request->query('industry');
            $statusQuery->whereHas('categories', function ($cq) use ($industry) {
                $cq->where('industry', $industry)
                   ->orWhere('name', 'like', "%{$industry}%")
                   ->orWhere('slug', 'like', "%" . Str::slug($industry) . "%");
            });
        }

        if ($search = $request->query('q')) {
            $statusQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content_normalized', 'like', "%{$search}%");
            });
        }

        $statusCounts = $statusQuery
            ->selectRaw('verification_status, count(*) as count')
            ->groupBy('verification_status')
            ->pluck('count', 'verification_status')
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => $articles->items(),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'total' => $articles->total(),
                'available_statuses' => $statusCounts,
            ]
        ]);
    }

    /**
     * Show deep intelligence analysis with all 10 core dimensions.
     */
    public function show(string $slug): JsonResponse
    {
        $article = Article::with([
            'source',
            'categories',
            'companies.executives',
            'summary',
            'assessment',
        ])->where('slug', $slug)->first();

        if (!$article) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ARTICLE_NOT_FOUND',
                    'message' => 'The specified intelligence article could not be found.',
                ]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $article,
        ]);
    }

    /**
     * Get breaking intelligence and high-impact developments.
     */
    public function breaking(): JsonResponse
    {
        $articles = Article::with(['source:id,name', 'categories:id,name,color'])
            ->where('is_breaking', true)
            ->whereIn('verification_status', ['confirmed', 'reported_reliable'])
            ->latest('published_at')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $articles,
        ]);
    }
}
