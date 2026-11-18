<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController
{
    /**
     * List intelligence categories with published article counts, industries, and subcategories.
     * Guaranteed to hide empty categories/industries so the interface never looks incomplete.
     */
    public function index(Request $request): JsonResponse
    {
        $hasArticlesOnly = $request->boolean('has_articles', true);
        $industryFilter = $request->query('industry');

        $query = Category::query()
            ->where('is_active', true)
            ->withCount(['articles' => function ($q) {
                $q->where('status', 'PUBLISHED');
            }])
            ->orderBy('sort_order', 'asc');

        if ($hasArticlesOnly) {
            $query->whereHas('articles', function ($q) {
                $q->where('status', 'PUBLISHED');
            });
        }

        if (!empty($industryFilter) && $industryFilter !== 'all') {
            $query->where(function ($q) use ($industryFilter) {
                $q->where('industry', $industryFilter)
                  ->orWhere('name', 'like', "%{$industryFilter}%");
            });
        }

        $categories = $query->get()->map(function ($cat) use ($hasArticlesOnly) {
            // Retrieve real subcategories that actually contain published articles
            $subQuery = $cat->subcategories()
                ->where('is_active', true)
                ->withCount(['articles' => function ($q) {
                    $q->where('status', 'PUBLISHED');
                }]);

            if ($hasArticlesOnly) {
                $subQuery->whereHas('articles', function ($q) {
                    $q->where('status', 'PUBLISHED');
                });
            }

            $subcategories = $subQuery->get()->map(function ($sub) {
                return [
                    'id' => $sub->id,
                    'name' => $sub->name,
                    'slug' => $sub->slug,
                    'article_count' => $sub->articles_count,
                ];
            })->toArray();

            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'industry' => $cat->industry,
                'description' => $cat->description,
                'color' => $cat->color,
                'article_count' => (int) $cat->articles_count,
                'subcategories' => $subcategories,
            ];
        });

        // Compute available industries with article counts
        $industries = [];
        foreach ($categories as $cat) {
            $ind = $cat['industry'] ?? 'Technology';
            if (!isset($industries[$ind])) {
                $industries[$ind] = [
                    'name' => $ind,
                    'slug' => Str::slug($ind),
                    'article_count' => 0,
                    'categories_count' => 0,
                ];
            }
            $industries[$ind]['article_count'] += $cat['article_count'];
            $industries[$ind]['categories_count']++;
        }

        return response()->json([
            'success' => true,
            'data' => $categories->values(),
            'meta' => [
                'industries' => array_values($industries),
                'total_categories' => $categories->count(),
            ],
        ]);
    }
}
