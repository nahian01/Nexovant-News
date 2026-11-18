<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Category;
use App\Models\Company;
use App\Models\Subcategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompanyAdminController
{
    /**
     * List all companies with multi-dimensional geo-hierarchy and industry taxonomy filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Company::with([
            'category:id,name,slug,color,industry',
            'subcategory:id,name,slug',
        ])->withCount(['articles', 'executives']);

        // Search Keyword
        if ($search = $request->query('q') ?? $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('ticker', 'like', "%{$search}%")
                  ->orWhere('isin', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('upazila', 'like', "%{$search}%")
                  ->orWhere('district', 'like', "%{$search}%")
                  ->orWhere('division', 'like', "%{$search}%")
                  ->orWhere('state', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%");
            });
        }

        // Continental & National Filtering
        if ($continent = $request->query('continent')) {
            $query->where('continent', $continent);
        }

        if ($country = $request->query('country')) {
            $query->where(function ($q) use ($country) {
                $q->where('country', $country)
                  ->orWhere('headquarters_country', strtoupper($country));
            });
        }

        // Administrative Level 1 (Division, State, Province)
        if ($division = $request->query('division')) {
            $query->where(function ($q) use ($division) {
                $q->where('division', $division)
                  ->orWhere(fn($sq) => $sq->where('admin_level_1_type', 'division')->where('admin_level_1', $division));
            });
        }

        if ($state = $request->query('state')) {
            $query->where(function ($q) use ($state) {
                $q->where('state', $state)
                  ->orWhere(fn($sq) => $sq->where('admin_level_1_type', 'state')->where('admin_level_1', $state));
            });
        }

        if ($province = $request->query('province')) {
            $query->where(function ($q) use ($province) {
                $q->where('province', $province)
                  ->orWhere(fn($sq) => $sq->where('admin_level_1_type', 'province')->where('admin_level_1', $province));
            });
        }

        if ($l1 = $request->query('admin_level_1')) {
            $query->where('admin_level_1', $l1);
        }

        // Administrative Level 2 (District, County)
        if ($district = $request->query('district')) {
            $query->where(function ($q) use ($district) {
                $q->where('district', $district)
                  ->orWhere('admin_level_2', $district);
            });
        }

        if ($county = $request->query('county')) {
            $query->where(function ($q) use ($county) {
                $q->where('county', $county)
                  ->orWhere('admin_level_2', $county);
            });
        }

        if ($l2 = $request->query('admin_level_2')) {
            $query->where('admin_level_2', $l2);
        }

        // Administrative Level 3 (Upazila, City)
        if ($upazila = $request->query('upazila')) {
            $query->where(function ($q) use ($upazila) {
                $q->where('upazila', $upazila)
                  ->orWhere(fn($sq) => $sq->where('admin_level_3_type', 'upazila')->where('admin_level_3', $upazila));
            });
        }

        if ($city = $request->query('city')) {
            $query->where(function ($q) use ($city) {
                $q->where('city', $city)
                  ->orWhere('admin_level_3', $city);
            });
        }

        if ($l3 = $request->query('admin_level_3')) {
            $query->where('admin_level_3', $l3);
        }

        // Industry & Category Filtering
        if ($industry = $request->query('industry')) {
            $query->where('industry', $industry);
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($categorySlug = $request->query('category_slug')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $categorySlug));
        }

        if ($marketCap = $request->query('market_cap_tier')) {
            $query->where('market_cap_tier', $marketCap);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Sort order
        $sortField = $request->query('sort_by', 'name');
        $sortDir = strtolower($request->query('sort_direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (in_array($sortField, ['name', 'country', 'continent', 'industry', 'market_cap_tier', 'created_at', 'employee_count'])) {
            $query->orderBy($sortField, $sortDir);
        } else {
            $query->orderBy('name', 'asc');
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 20)));
        $companies = $query->paginate($perPage);

        // Build comprehensive breakdown metadata for filters
        $continents = Company::whereNotNull('continent')
            ->selectRaw('continent, count(*) as count')
            ->groupBy('continent')
            ->orderBy('count', 'desc')
            ->get();

        $countries = Company::whereNotNull('country')
            ->selectRaw('country, headquarters_country, continent, count(*) as count')
            ->groupBy('country', 'headquarters_country', 'continent')
            ->orderBy('count', 'desc')
            ->get();

        $divisions = Company::whereNotNull('division')
            ->selectRaw('division, count(*) as count')
            ->groupBy('division')
            ->orderBy('count', 'desc')
            ->get();

        $districts = Company::whereNotNull('district')
            ->selectRaw('district, division, country, count(*) as count')
            ->groupBy('district', 'division', 'country')
            ->orderBy('count', 'desc')
            ->get();

        $upazilas = Company::whereNotNull('upazila')
            ->selectRaw('upazila, district, count(*) as count')
            ->groupBy('upazila', 'district')
            ->orderBy('count', 'desc')
            ->get();

        $industries = Company::whereNotNull('industry')
            ->selectRaw('industry, count(*) as count')
            ->groupBy('industry')
            ->orderBy('count', 'desc')
            ->get();

        $categories = Category::where('is_active', true)
            ->withCount('articles')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'industry', 'color']);

        return response()->json([
            'success' => true,
            'data' => $companies->items(),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
                'aggregates' => [
                    'continents' => $continents,
                    'countries' => $countries,
                    'divisions' => $divisions,
                    'districts' => $districts,
                    'upazilas' => $upazilas,
                    'industries' => $industries,
                    'categories' => $categories,
                ],
            ],
        ]);
    }

    /**
     * Show single company with related executives, category, and articles.
     */
    public function show(string $id): JsonResponse
    {
        $company = Company::with([
            'category',
            'subcategory',
            'executives',
            'competitors',
            'articles' => fn($q) => $q->latest('published_at')->limit(10),
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $company,
        ]);
    }

    /**
     * Create a new company with full multi-dimensional taxonomy and geo-hierarchy.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:companies,slug'],
            'ticker' => ['nullable', 'string', 'max:20'],
            'isin' => ['nullable', 'string', 'max:12'],
            'sector' => ['nullable', 'string', 'max:150'],
            'industry' => ['nullable', 'string', 'max:150'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'uuid', 'exists:subcategories,id'],
            'continent' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'headquarters_country' => ['nullable', 'string', 'max:2'],
            'state' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'division' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'county' => ['nullable', 'string', 'max:100'],
            'upazila' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'admin_level_1_type' => ['nullable', 'string', 'max:30'],
            'admin_level_1' => ['nullable', 'string', 'max:100'],
            'admin_level_2_type' => ['nullable', 'string', 'max:30'],
            'admin_level_2' => ['nullable', 'string', 'max:100'],
            'admin_level_3_type' => ['nullable', 'string', 'max:30'],
            'admin_level_3' => ['nullable', 'string', 'max:100'],
            'headquarters_address' => ['nullable', 'string'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:255'],
            'market_cap_tier' => ['nullable', 'string', 'in:mega,large,mid,small,micro'],
            'employee_count' => ['nullable', 'integer', 'min:1'],
            'founded_year' => ['nullable', 'integer', 'min:1600', 'max:2030'],
            'summary' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            // Ensure unique slug
            $originalSlug = $validated['slug'];
            $counter = 1;
            while (Company::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = "{$originalSlug}-{$counter}";
                $counter++;
            }
        }

        $company = Company::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Corporate entity created successfully.',
            'data' => $company->fresh(['category', 'subcategory']),
        ], 201);
    }

    /**
     * Update existing company with all geo-administrative dimensions.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $company = Company::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('companies', 'slug')->ignore($company->id)],
            'ticker' => ['nullable', 'string', 'max:20'],
            'isin' => ['nullable', 'string', 'max:12'],
            'sector' => ['nullable', 'string', 'max:150'],
            'industry' => ['nullable', 'string', 'max:150'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'uuid', 'exists:subcategories,id'],
            'continent' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'headquarters_country' => ['nullable', 'string', 'max:2'],
            'state' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'division' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'county' => ['nullable', 'string', 'max:100'],
            'upazila' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'admin_level_1_type' => ['nullable', 'string', 'max:30'],
            'admin_level_1' => ['nullable', 'string', 'max:100'],
            'admin_level_2_type' => ['nullable', 'string', 'max:30'],
            'admin_level_2' => ['nullable', 'string', 'max:100'],
            'admin_level_3_type' => ['nullable', 'string', 'max:30'],
            'admin_level_3' => ['nullable', 'string', 'max:100'],
            'headquarters_address' => ['nullable', 'string'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:255'],
            'market_cap_tier' => ['nullable', 'string', 'in:mega,large,mid,small,micro'],
            'employee_count' => ['nullable', 'integer', 'min:1'],
            'founded_year' => ['nullable', 'integer', 'min:1600', 'max:2030'],
            'summary' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $company->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Corporate entity updated successfully.',
            'data' => $company->fresh(['category', 'subcategory']),
        ]);
    }

    /**
     * Delete company from registry.
     */
    public function destroy(string $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $company->delete();

        return response()->json([
            'success' => true,
            'message' => 'Corporate entity deleted successfully.',
        ]);
    }

    /**
     * Retrieve geo-administrative hierarchy definitions and country nomenclature rules.
     */
    public function geoHierarchyOptions(): JsonResponse
    {
        $nomenclature = [
            'Bangladesh' => [
                'continent' => 'Asia',
                'code' => 'BD',
                'level_1' => 'Division',
                'level_2' => 'District',
                'level_3' => 'Upazila / Thana',
                'common_divisions' => [
                    'Dhaka', 'Chattogram', 'Sylhet', 'Rajshahi', 'Khulna', 'Barishal', 'Rangpur', 'Mymensingh'
                ],
            ],
            'United States' => [
                'continent' => 'North America',
                'code' => 'US',
                'level_1' => 'State',
                'level_2' => 'County',
                'level_3' => 'City',
                'common_divisions' => [
                    'California', 'Washington', 'Texas', 'New York', 'Massachusetts', 'Illinois', 'Florida'
                ],
            ],
            'Canada' => [
                'continent' => 'North America',
                'code' => 'CA',
                'level_1' => 'Province',
                'level_2' => 'Regional District',
                'level_3' => 'City / Municipality',
                'common_divisions' => ['Ontario', 'British Columbia', 'Quebec', 'Alberta'],
            ],
            'United Kingdom' => [
                'continent' => 'Europe',
                'code' => 'GB',
                'level_1' => 'Region / Country',
                'level_2' => 'County',
                'level_3' => 'City / Borough',
                'common_divisions' => ['England', 'Scotland', 'Wales', 'Northern Ireland'],
            ],
            'Germany' => [
                'continent' => 'Europe',
                'code' => 'DE',
                'level_1' => 'Federal State (Land)',
                'level_2' => 'District (Kreis)',
                'level_3' => 'City / Municipality',
                'common_divisions' => ['Baden-Württemberg', 'Bavaria', 'Berlin', 'North Rhine-Westphalia', 'Hesse'],
            ],
            'Japan' => [
                'continent' => 'Asia',
                'code' => 'JP',
                'level_1' => 'Prefecture',
                'level_2' => 'District / Subprefecture',
                'level_3' => 'City / Ward',
                'common_divisions' => ['Tokyo', 'Kanagawa', 'Osaka', 'Aichi', 'Kyoto', 'Fukuoka'],
            ],
            'India' => [
                'continent' => 'Asia',
                'code' => 'IN',
                'level_1' => 'State',
                'level_2' => 'District',
                'level_3' => 'Taluk / City',
                'common_divisions' => ['Karnataka', 'Maharashtra', 'Delhi', 'Tamil Nadu', 'Telangana'],
            ],
            'Switzerland' => [
                'continent' => 'Europe',
                'code' => 'CH',
                'level_1' => 'Canton',
                'level_2' => 'District',
                'level_3' => 'City / Municipality',
                'common_divisions' => ['Basel-Stadt', 'Zurich', 'Geneva', 'Vaud', 'Bern'],
            ],
        ];

        $continents = [
            'Asia',
            'Europe',
            'North America',
            'South America',
            'Africa',
            'Oceania',
        ];

        return response()->json([
            'success' => true,
            'continents' => $continents,
            'nomenclature' => $nomenclature,
        ]);
    }
}
