<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController
{
    /**
     * List all intelligence categories with article counts and subcategories.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->withCount('articles')
            ->orderBy('sort_order', 'asc')
            ->get()
            ->map(function ($cat) {
                // Dynamically associate relevant subcategories
                $subcategories = match ($cat->slug) {
                    'expansion-capex' => ['Foundry & Fabs', 'Data Centers', 'Geographic Expansion', 'Joint Ventures'],
                    'leadership-transitions' => ['CEO Transitions', 'Boardroom Moves', 'CFO & Finance', 'Executive Resignations'],
                    'financial-performance' => ['Earnings Disclosures', 'Guidance Revisions', 'Credit Ratings', 'Revenue Misses'],
                    'mergers-acquisitions' => ['Strategic Buyouts', 'Antitrust Review', 'Hostile Bids', 'Divestitures'],
                    'emerging-risks' => ['Sanctions & Geopolitics', 'Supply Chain Snarls', 'Patent Litigation', 'Regulatory Scrutiny'],
                    default => ['Strategic Updates', 'Market Moves', 'General Analysis']
                };

                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'description' => $cat->description,
                    'color' => $cat->color,
                    'article_count' => $cat->articles_count,
                    'subcategories' => $subcategories,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}
