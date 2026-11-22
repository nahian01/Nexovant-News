<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\FollowedCompany;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyGeoTaxonomyAndPersonalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Category::create([
            'name' => 'Strategic Technology',
            'slug' => 'strategic-technology',
            'industry' => 'Technology',
            'color' => '#147A67',
            'is_active' => true,
        ]);

        $this->artisan('nexovant:seed-global-companies --force');
    }

    /**
     * Test taxonomy endpoint returns multi-level continents, countries, administrative divisions, and industries.
     */
    public function test_taxonomy_endpoint_returns_hierarchical_data(): void
    {
        $response = $this->getJson('/api/v1/companies/taxonomy');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'continents',
                    'countries',
                    'divisions',
                    'districts',
                    'upazilas',
                    'industries',
                    'sectors',
                    'market_cap_tiers',
                    'user' => [
                        'is_authenticated',
                        'followed_company_ids',
                        'preferred_industries',
                    ]
                ]
            ]);

        $continents = $response->json('data.continents');
        $this->assertContains('Asia', $continents);
        $this->assertContains('North America', $continents);
        $this->assertContains('Europe', $continents);
        $this->assertContains('Africa', $continents);
        $this->assertContains('Oceania', $continents);
        $this->assertContains('South America', $continents);
    }

    /**
     * Test companies index supports for_you tab and personalization ranking.
     */
    public function test_companies_index_for_you_tab_and_personalization(): void
    {
        $user = User::factory()->create();
        UserPreference::create([
            'user_id' => $user->id,
            'preferred_industries' => ['Accelerated Computing & AI Silicon', 'Cloud Infrastructure & Enterprise AI'],
            'personalization_enabled' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/companies?tab=for_you');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'total',
                    'per_page',
                    'tab',
                ]
            ]);

        $companies = $response->json('data');
        $this->assertNotEmpty($companies);

        // Check is_followed attribute is present on all items
        foreach ($companies as $c) {
            $this->assertArrayHasKey('is_followed', $c);
            $this->assertArrayHasKey('admin_labels', $c);
        }
    }

    /**
     * Test multi-level geographic filtering down to division, district, and upazila.
     */
    public function test_multi_level_geo_filtering(): void
    {
        // 1. Filter by continent Oceania
        $oceaniaResponse = $this->getJson('/api/v1/companies?continent=Oceania');
        $oceaniaResponse->assertStatus(200);
        $oceaniaData = $oceaniaResponse->json('data');
        $this->assertNotEmpty($oceaniaData);
        foreach ($oceaniaData as $c) {
            $this->assertEquals('Oceania', $c['continent']);
        }

        // 2. Filter by country Bangladesh and division Dhaka
        $bdResponse = $this->getJson('/api/v1/companies?country=Bangladesh&division=Dhaka');
        $bdResponse->assertStatus(200);
        $bdData = $bdResponse->json('data');
        $this->assertNotEmpty($bdData);
        foreach ($bdData as $c) {
            $this->assertEquals('Bangladesh', $c['country']);
            $this->assertEquals('Dhaka', $c['division'] ?? $c['admin_level_1']);
        }

        // 3. Filter by market_cap_tier mega
        $megaResponse = $this->getJson('/api/v1/companies?market_cap_tier=mega');
        $megaResponse->assertStatus(200);
        $megaData = $megaResponse->json('data');
        $this->assertNotEmpty($megaData);
        foreach ($megaData as $c) {
            $this->assertEquals('mega', $c['market_cap_tier']);
        }
    }

    /**
     * Test company detail by slug returns company profile and is_followed boolean.
     */
    public function test_company_show_returns_profile(): void
    {
        $company = Company::first();
        $this->assertNotNull($company);

        $response = $this->getJson("/api/v1/companies/{$company->slug}");
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', $company->name)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'ticker',
                    'continent',
                    'country',
                    'admin_labels',
                    'is_followed',
                ]
            ]);
    }

    /**
     * Test toggle follow adds and removes company from user watchlist.
     */
    public function test_toggle_follow_company(): void
    {
        $user = User::factory()->create();
        $company = Company::first();

        // 1. Follow
        $followRes = $this->actingAs($user, 'sanctum')->postJson("/api/v1/companies/{$company->id}/follow");
        $followRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_followed', true);

        $this->assertDatabaseHas('followed_companies', [
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        // 2. Unfollow
        $unfollowRes = $this->actingAs($user, 'sanctum')->postJson("/api/v1/companies/{$company->id}/follow");
        $unfollowRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_followed', false);

        $this->assertDatabaseMissing('followed_companies', [
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);
    }

    /**
     * Test that user preferences with SQL injection payloads are safely parameterized and executed.
     */
    public function test_personalization_sorting_safely_handles_sql_injection_vectors(): void
    {
        $user = User::factory()->create();

        UserPreference::create([
            'user_id' => $user->id,
            'preferred_industries' => [
                "Technology') THEN 100 ELSE 0 END --",
                "Semiconductors' OR '1'='1",
                "'; DROP TABLE companies; --",
                "Arbitrary'\"\\`/*",
            ],
            'personalization_enabled' => true,
        ]);

        $initialCompanyCount = Company::count();
        $this->assertGreaterThan(0, $initialCompanyCount);

        // Fetch personalized list with malicious preferences
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/companies?tab=for_you');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Ensure database integrity is maintained and no tables dropped
        $this->assertEquals($initialCompanyCount, Company::count());
    }
}
