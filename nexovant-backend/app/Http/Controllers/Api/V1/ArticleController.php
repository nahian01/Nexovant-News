<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController
{
    /**
     * List verified and developing intelligence articles.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Article::with(['source:id,name,credibility_score', 'categories:id,name,slug,color', 'companies:id,name,ticker'])
            ->latest('published_at');

        if ($request->has('category')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('slug', $request->query('category'));
            });
        }

        if ($request->has('verification_status')) {
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

        $articles = $query->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $articles->items(),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'total' => $articles->total(),
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
