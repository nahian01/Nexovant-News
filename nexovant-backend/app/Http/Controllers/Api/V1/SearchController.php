<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Article;
use App\Models\Company;
use App\Models\Executive;
use App\Models\SocialTrend;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController
{
    /**
     * Global multi-entity search.
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        if (strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'data' => [
                    'articles' => [],
                    'companies' => [],
                    'executives' => [],
                    'trends' => [],
                ],
            ]);
        }

        $articles = Article::query()
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('content_normalized', 'like', "%{$query}%");
            })
            ->with(['source', 'categories', 'companies'])
            ->limit(8)
            ->get();

        $companies = Company::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('ticker', 'like', "%{$query}%")
                  ->orWhere('sector', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

        $executives = Executive::query()
            ->where(function ($q) use ($query) {
                $q->where('full_name', 'like', "%{$query}%")
                  ->orWhere('current_title', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

        $trends = SocialTrend::query()
            ->where('title', 'like', "%{$query}%")
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'query' => $query,
            'data' => [
                'articles' => $articles,
                'companies' => $companies,
                'executives' => $executives,
                'trends' => $trends,
            ],
        ]);
    }

    /**
     * Fast autocomplete suggestions for search bar dropdown.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        if (strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'suggestions' => [
                    ['text' => 'NVIDIA EMEA Foundry Expansion', 'type' => 'article', 'slug' => 'nvidia-custom-silicon-foundry-emea'],
                    ['text' => 'European Semiconductor Sovereignty', 'type' => 'trend', 'id' => '01a1d92a-7e1b-71e4-815d-c71afb55d832'],
                    ['text' => 'Jensen Huang (NVIDIA CEO)', 'type' => 'executive', 'slug' => 'jensen-huang'],
                    ['text' => 'Novartis AG Executive Transition', 'type' => 'company', 'slug' => 'novartis-ag'],
                ],
            ]);
        }

        $suggestions = [];

        // Matching Articles
        $articles = Article::where('title', 'like', "%{$query}%")->limit(3)->get();
        foreach ($articles as $a) {
            $suggestions[] = ['text' => $a->title, 'type' => 'article', 'slug' => $a->slug];
        }

        // Matching Companies
        $companies = Company::where('name', 'like', "%{$query}%")->orWhere('ticker', 'like', "%{$query}%")->limit(2)->get();
        foreach ($companies as $c) {
            $suggestions[] = ['text' => "{$c->name} ({$c->ticker})", 'type' => 'company', 'slug' => $c->slug];
        }

        // Matching Executives
        $executives = Executive::where('full_name', 'like', "%{$query}%")->limit(2)->get();
        foreach ($executives as $e) {
            $suggestions[] = ['text' => "{$e->full_name} ({$e->current_title})", 'type' => 'executive', 'slug' => $e->slug];
        }

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions,
        ]);
    }
}
