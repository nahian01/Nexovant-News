<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Company;
use App\Models\FollowedCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyController
{
    /**
     * List global enterprise companies with multi-level geo and industry taxonomy filtering and personalization.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();
        $followedIds = $user ? FollowedCompany::where('user_id', $user->id)->pluck('company_id')->toArray() : [];

        $query = Company::with([
            'category:id,name,slug,color,industry',
            'subcategory:id,name,slug',
        ])->withCount(['articles', 'executives'])
          ->where('is_active', true);

        $tab = $request->query('tab', 'for_you');

        // Tab-specific filters & constraints
        if ($tab === 'following' || $tab === 'watchlist') {
            if (!$user) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'total' => 0,
                        'per_page' => (int) $request->query('per_page', 24),
                        'tab' => $tab,
                    ]
                ]);
            }
            $query->whereIn('id', $followedIds);
        } elseif ($tab === 'global_giants') {
            $query->where('market_cap_tier', 'mega');
        } elseif ($tab === 'national_champions') {
            $query->whereIn('market_cap_tier', ['large', 'mid']);
        }

        // Multi-level geographic filtering
        if ($continent = $request->query('continent')) {
            if ($continent !== 'All Continents') {
                $query->where('continent', $continent);
            }
        }

        if ($country = $request->query('country')) {
            if ($country !== 'All Countries') {
                $query->where(function ($q) use ($country) {
                    $q->where('country', $country)
                      ->orWhere('headquarters_country', strtoupper($country));
                });
            }
        }

        if ($division = $request->query('division')) {
            if ($division !== 'All Divisions' && $division !== 'All States') {
                $query->where(function ($q) use ($division) {
                    $q->where('division', $division)
                      ->orWhere('state', $division)
                      ->orWhere('province', $division)
                      ->orWhere('admin_level_1', $division);
                });
            }
        }

        if ($district = $request->query('district')) {
            if ($district !== 'All Districts' && $district !== 'All Counties') {
                $query->where(function ($q) use ($district) {
                    $q->where('district', $district)
                      ->orWhere('county', $district)
                      ->orWhere('admin_level_2', $district);
                });
            }
        }

        if ($upazila = $request->query('upazila')) {
            if ($upazila !== 'All Upazilas' && $upazila !== 'All Cities') {
                $query->where(function ($q) use ($upazila) {
                    $q->where('upazila', $upazila)
                      ->orWhere('city', $upazila)
                      ->orWhere('admin_level_3', $upazila);
                });
            }
        }

        if ($state = $request->query('state')) {
            $query->where(function ($q) use ($state) {
                $q->where('state', $state)
                  ->orWhere('admin_level_1', $state);
            });
        }

        // Taxonomy & Market Filters
        if ($industry = $request->query('industry')) {
            if ($industry !== 'All Industries') {
                $query->where('industry', $industry);
            }
        }

        if ($sector = $request->query('sector')) {
            if ($sector !== 'All Sectors') {
                $query->where('sector', $sector);
            }
        }

        if ($categorySlug = $request->query('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $categorySlug));
        }

        if ($tier = $request->query('market_cap_tier')) {
            if ($tier !== 'all') {
                $query->where('market_cap_tier', $tier);
            }
        }

        // Free-text Search
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('ticker', 'like', "%{$search}%")
                  ->orWhere('industry', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('upazila', 'like', "%{$search}%")
                  ->orWhere('district', 'like', "%{$search}%")
                  ->orWhere('division', 'like', "%{$search}%")
                  ->orWhere('state', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        // Personalized vs Standard Sorting
        if ($tab === 'for_you') {
            $preferredIndustries = $user?->preferences?->preferred_industries ?? [];
            if (empty($preferredIndustries)) {
                // Default high-impact developer and technology professional profile
                $preferredIndustries = [
                    'Accelerated Computing & AI Silicon',
                    'Cloud Infrastructure & Enterprise AI',
                    'Artificial Intelligence & Search Infrastructure',
                    'Developer Collaboration & Agile Management',
                    'Semiconductor Microprocessors & Foundry Services',
                    'EUV Semiconductor Lithography',
                    'Pure-Play Pure Semiconductor Foundry',
                    'Open Source AI & Social Platforms',
                    'Enterprise SaaS & Autonomous Agent Platforms',
                    'Cloud Computing (AWS) & Digital Commerce',
                    'Consumer Electronics & Devices',
                    'Digital Neobanking & FinTech Platforms',
                    'Urban On-Demand Mobility, Logistics & FinTech',
                ];
            }

            $orderClauses = [];

            if (!empty($followedIds)) {
                $sanitizedFollowed = array_map(fn($id) => "'" . addslashes((string)$id) . "'", $followedIds);
                $orderClauses[] = "CASE WHEN id IN (" . implode(',', $sanitizedFollowed) . ") THEN 100 ELSE 0 END";
            }

            if (!empty($preferredIndustries)) {
                $sanitizedIndustries = array_map(fn($ind) => "'" . addslashes((string)$ind) . "'", $preferredIndustries);
                $orderClauses[] = "CASE WHEN industry IN (" . implode(',', $sanitizedIndustries) . ") THEN 60 ELSE 0 END";
            }

            $orderClauses[] = "CASE WHEN market_cap_tier = 'mega' THEN 25 WHEN market_cap_tier = 'large' THEN 15 ELSE 5 END";

            $orderSql = '(' . implode(' + ', $orderClauses) . ') DESC';
            $query->orderByRaw($orderSql)->orderBy('name', 'asc');
        } elseif ($tab === 'global_giants') {
            $query->orderByDesc('employee_count')->orderBy('name', 'asc');
        } elseif ($tab === 'national_champions') {
            $query->orderByRaw("CASE WHEN country = 'Bangladesh' THEN 1 WHEN country = 'India' THEN 2 ELSE 3 END")
                  ->orderByDesc('articles_count')
                  ->orderBy('name', 'asc');
        } else {
            // 'all' tab: Global Impact first (mega then large then mid), then alphabetical
            $query->orderByRaw("CASE WHEN market_cap_tier = 'mega' THEN 1 WHEN market_cap_tier = 'large' THEN 2 ELSE 3 END")
                  ->orderBy('name', 'asc');
        }

        $perPage = (int) $request->query('per_page', 24);
        $companies = $query->paginate($perPage);

        $items = collect($companies->items())->map(function ($company) use ($followedIds) {
            $data = $company->toArray();
            $data['is_followed'] = in_array($company->id, $followedIds, true);
            return $data;
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'total' => $companies->total(),
                'per_page' => $companies->perPage(),
                'tab' => $tab,
            ]
        ]);
    }

    /**
     * Retrieve dynamic geographic and industry taxonomy options based on real company database records.
     */
    public function taxonomy(Request $request): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();

        // 1. Continents
        $continents = Company::where('is_active', true)
            ->whereNotNull('continent')
            ->distinct()
            ->orderBy('continent')
            ->pluck('continent')
            ->toArray();

        // 2. Countries grouped by continent
        $countries = Company::where('is_active', true)
            ->whereNotNull('country')
            ->select('country', 'continent', 'headquarters_country', DB::raw('count(*) as count'))
            ->groupBy('country', 'continent', 'headquarters_country')
            ->orderBy('country')
            ->get();

        // 3. Administrative Level 1 (Divisions / States / Provinces)
        $divisions = Company::where('is_active', true)
            ->whereNotNull('admin_level_1')
            ->select('admin_level_1 as name', 'admin_level_1_type as type', 'country', 'continent', DB::raw('count(*) as count'))
            ->groupBy('admin_level_1', 'admin_level_1_type', 'country', 'continent')
            ->orderBy('admin_level_1')
            ->get();

        // 4. Administrative Level 2 (Districts / Counties)
        $districts = Company::where('is_active', true)
            ->whereNotNull('admin_level_2')
            ->select('admin_level_2 as name', 'admin_level_2_type as type', 'country', DB::raw('count(*) as count'))
            ->groupBy('admin_level_2', 'admin_level_2_type', 'country')
            ->orderBy('admin_level_2')
            ->get();

        // 5. Administrative Level 3 (Upazilas / Cities)
        $upazilas = Company::where('is_active', true)
            ->whereNotNull('admin_level_3')
            ->select('admin_level_3 as name', 'admin_level_3_type as type', 'country', DB::raw('count(*) as count'))
            ->groupBy('admin_level_3', 'admin_level_3_type', 'country')
            ->orderBy('admin_level_3')
            ->get();

        // 6. Industries
        $industries = Company::where('is_active', true)
            ->whereNotNull('industry')
            ->select('industry as name', 'sector', DB::raw('count(*) as count'))
            ->groupBy('industry', 'sector')
            ->orderByDesc('count')
            ->get();

        // 7. Sectors
        $sectors = Company::where('is_active', true)
            ->whereNotNull('sector')
            ->select('sector as name', DB::raw('count(*) as count'))
            ->groupBy('sector')
            ->orderByDesc('count')
            ->get();

        // 8. Market Cap Tiers
        $marketCapTiers = Company::where('is_active', true)
            ->whereNotNull('market_cap_tier')
            ->select('market_cap_tier as tier', DB::raw('count(*) as count'))
            ->groupBy('market_cap_tier')
            ->get();

        // 9. User Preferences & Follows
        $followedCompanyIds = [];
        $preferredIndustries = [];

        if ($user) {
            $followedCompanyIds = FollowedCompany::where('user_id', $user->id)->pluck('company_id')->toArray();
            $preferredIndustries = $user->preferences?->preferred_industries ?? [];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'continents' => $continents,
                'countries' => $countries,
                'divisions' => $divisions,
                'districts' => $districts,
                'upazilas' => $upazilas,
                'industries' => $industries,
                'sectors' => $sectors,
                'market_cap_tiers' => $marketCapTiers,
                'user' => [
                    'is_authenticated' => (bool) $user,
                    'followed_company_ids' => $followedCompanyIds,
                    'preferred_industries' => $preferredIndustries,
                ]
            ]
        ]);
    }

    /**
     * Show comprehensive company profile with leadership and competitors.
     */
    public function show(string $slug, Request $request): JsonResponse
    {
        $company = Company::with([
            'category:id,name,slug,color,industry',
            'subcategory:id,name,slug',
            'executives',
            'competitors:id,name,slug,ticker,sector',
            'articles' => function ($q) {
                $q->latest('published_at')->limit(10);
            }
        ])->where('slug', $slug)
          ->orWhere('ticker', strtoupper($slug))
          ->first();

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
        $user = $request->user('sanctum') ?? $request->user();
        if ($user) {
            $isFollowed = FollowedCompany::where('user_id', $user->id)
                ->where('company_id', $company->id)
                ->exists();
        }

        $data = $company->toArray();
        $data['is_followed'] = $isFollowed;

        return response()->json([
            'success' => true,
            'data' => $data,
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
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required to follow companies.',
                ]
            ], 401);
        }

        $company = Company::findOrFail($id);

        $existing = FollowedCompany::where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json([
                'success' => true,
                'message' => "Unfollowed {$company->name} successfully.",
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
            'message' => "{$company->name} added to your executive watchlist.",
            'is_followed' => true,
        ]);
    }
}
