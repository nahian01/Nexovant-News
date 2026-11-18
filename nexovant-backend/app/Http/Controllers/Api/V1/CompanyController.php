<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Company;
use App\Models\FollowedCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController
{
    /**
     * List global enterprise companies with multi-level geo and industry taxonomy filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Company::with([
            'category:id,name,slug,color,industry',
            'subcategory:id,name,slug',
        ])->withCount(['articles', 'executives'])
          ->where('is_active', true);

        if ($sector = $request->query('sector')) {
            $query->where('sector', $sector);
        }

        if ($industry = $request->query('industry')) {
            $query->where('industry', $industry);
        }

        if ($continent = $request->query('continent')) {
            $query->where('continent', $continent);
        }

        if ($country = $request->query('country')) {
            $query->where(function ($q) use ($country) {
                $q->where('country', $country)
                  ->orWhere('headquarters_country', strtoupper($country));
            });
        }

        if ($division = $request->query('division')) {
            $query->where(function ($q) use ($division) {
                $q->where('division', $division)
                  ->orWhere(fn($sq) => $sq->where('admin_level_1_type', 'division')->where('admin_level_1', $division));
            });
        }

        if ($district = $request->query('district')) {
            $query->where(function ($q) use ($district) {
                $q->where('district', $district)
                  ->orWhere('admin_level_2', $district);
            });
        }

        if ($upazila = $request->query('upazila')) {
            $query->where(function ($q) use ($upazila) {
                $q->where('upazila', $upazila)
                  ->orWhere(fn($sq) => $sq->where('admin_level_3_type', 'upazila')->where('admin_level_3', $upazila));
            });
        }

        if ($state = $request->query('state')) {
            $query->where(function ($q) use ($state) {
                $q->where('state', $state)
                  ->orWhere('admin_level_1', $state);
            });
        }

        if ($categorySlug = $request->query('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $categorySlug));
        }

        if ($tier = $request->query('market_cap_tier')) {
            $query->where('market_cap_tier', $tier);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('ticker', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('upazila', 'like', "%{$search}%")
                  ->orWhere('district', 'like', "%{$search}%")
                  ->orWhere('division', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%");
            });
        }

        $companies = $query->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $companies->items(),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'total' => $companies->total(),
            ]
        ]);
    }

    /**
     * Show comprehensive company profile with leadership and competitors.
     */
    public function show(string $slug, Request $request): JsonResponse
    {
        $company = Company::with([
            'executives',
            'competitors:id,name,slug,ticker,sector',
            'articles' => function ($q) {
                $q->latest('published_at')->limit(10);
            }
        ])->where('slug', $slug)->first();

        if (!$company) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'COMPANY_NOT_FOUND',
                    'message' => 'The specified corporate entity could not be found.',
                ]
            ], 404);
        }

        $isFollowed = false;
        if ($user = $request->user()) {
            $isFollowed = FollowedCompany::where('user_id', $user->id)
                ->where('company_id', $company->id)
                ->exists();
        }

        return response()->json([
            'success' => true,
            'data' => $company,
            'meta' => [
                'is_followed' => $isFollowed,
            ]
        ]);
    }

    /**
     * Toggle follow state on watchlist.
     */
    public function toggleFollow(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $company = Company::findOrFail($id);

        $existing = FollowedCompany::where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json([
                'success' => true,
                'message' => 'Unfollowed company successfully.',
                'is_followed' => false,
            ]);
        }

        FollowedCompany::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'notify_on_events' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Company added to executive watchlist.',
            'is_followed' => true,
        ]);
    }
}
