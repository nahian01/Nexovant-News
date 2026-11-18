<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $subscriber;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin-company@nexovant.test',
            'account_type' => 'admin',
        ]);

        $this->subscriber = User::factory()->create([
            'email' => 'sub-company@nexovant.test',
            'account_type' => 'subscriber',
        ]);

        $this->category = Category::create([
            'name' => 'Strategic Technology',
            'slug' => 'strategic-technology',
            'industry' => 'Technology',
            'color' => '#147A67',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_list_companies_with_geo_hierarchy_aggregates(): void
    {
        Company::create([
            'name' => 'Walton Hi-Tech',
            'slug' => 'walton-hi-tech',
            'continent' => 'Asia',
            'country' => 'Bangladesh',
            'division' => 'Dhaka',
            'district' => 'Gazipur',
            'upazila' => 'Kaliakair',
            'industry' => 'Technology',
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        Company::create([
            'name' => 'Apple Inc',
            'slug' => 'apple-inc',
            'continent' => 'North America',
            'country' => 'United States',
            'state' => 'California',
            'county' => 'Santa Clara County',
            'city' => 'Cupertino',
            'industry' => 'Technology',
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/companies');

        $res->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'continent',
                        'country',
                        'admin_level_1',
                        'admin_level_2',
                        'admin_level_3',
                        'geo_summary',
                        'geo_hierarchy',
                        'admin_labels',
                    ]
                ],
                'meta' => [
                    'total',
                    'aggregates' => [
                        'continents',
                        'countries',
                        'divisions',
                        'districts',
                        'upazilas',
                        'industries',
                    ]
                ]
            ]);

        $this->assertEquals(2, $res->json('meta.total'));
    }

    public function test_admin_can_filter_companies_by_bangladesh_division_district_upazila(): void
    {
        $walton = Company::create([
            'name' => 'Walton Hi-Tech',
            'slug' => 'walton-hi-tech',
            'continent' => 'Asia',
            'country' => 'Bangladesh',
            'division' => 'Dhaka',
            'district' => 'Gazipur',
            'upazila' => 'Kaliakair',
            'city' => 'Chandra',
            'industry' => 'Technology',
            'is_active' => true,
        ]);

        $square = Company::create([
            'name' => 'Square Pharma',
            'slug' => 'square-pharma',
            'continent' => 'Asia',
            'country' => 'Bangladesh',
            'division' => 'Dhaka',
            'district' => 'Pabna',
            'upazila' => 'Pabna Sadar',
            'city' => 'Pabna',
            'industry' => 'Healthcare',
            'is_active' => true,
        ]);

        // Filter by district: Gazipur
        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/companies?district=Gazipur');

        $res->assertStatus(200);
        $data = $res->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Walton Hi-Tech', $data[0]['name']);
        $this->assertEquals('Kaliakair', $data[0]['upazila']);
        $this->assertEquals('Division', $data[0]['admin_labels']['level_1']);
        $this->assertEquals('District', $data[0]['admin_labels']['level_2']);
        $this->assertEquals('Upazila / Thana', $data[0]['admin_labels']['level_3']);

        // Filter by upazila: Pabna Sadar
        $resUpazila = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/companies?upazila=Pabna Sadar');

        $resUpazila->assertStatus(200);
        $upazilaData = $resUpazila->json('data');
        $this->assertCount(1, $upazilaData);
        $this->assertEquals('Square Pharma', $upazilaData[0]['name']);
    }

    public function test_admin_can_filter_companies_by_us_state_county_city(): void
    {
        Company::create([
            'name' => 'Tesla Austin',
            'slug' => 'tesla-austin',
            'continent' => 'North America',
            'country' => 'United States',
            'state' => 'Texas',
            'county' => 'Travis County',
            'city' => 'Austin',
            'industry' => 'Automotive',
            'is_active' => true,
        ]);

        Company::create([
            'name' => 'NVIDIA Santa Clara',
            'slug' => 'nvidia-santa-clara',
            'continent' => 'North America',
            'country' => 'United States',
            'state' => 'California',
            'county' => 'Santa Clara County',
            'city' => 'Santa Clara',
            'industry' => 'Technology',
            'is_active' => true,
        ]);

        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/companies?state=Texas');

        $res->assertStatus(200);
        $data = $res->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Tesla Austin', $data[0]['name']);
        $this->assertEquals('State', $data[0]['admin_labels']['level_1']);
        $this->assertEquals('County', $data[0]['admin_labels']['level_2']);
        $this->assertEquals('City', $data[0]['admin_labels']['level_3']);
    }

    public function test_admin_can_create_company_with_bangladesh_hierarchy(): void
    {
        $payload = [
            'name' => 'Grameenphone Limited',
            'ticker' => 'GP',
            'sector' => 'Telecommunications',
            'industry' => 'Strategic Technology',
            'category_id' => $this->category->id,
            'continent' => 'Asia',
            'country' => 'Bangladesh',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'upazila' => 'Vatara',
            'city' => 'Dhaka',
            'headquarters_address' => 'GPHOUSE, Bashundhara, Baridhara, Dhaka-1229',
            'postal_code' => '1229',
            'market_cap_tier' => 'large',
            'employee_count' => 4500,
            'founded_year' => 1997,
            'summary' => 'Leading telecom operator in Bangladesh.',
        ];

        $res = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/companies', $payload);

        $res->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('companies', [
            'name' => 'Grameenphone Limited',
            'country' => 'Bangladesh',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'upazila' => 'Vatara',
            'admin_level_1_type' => 'division',
            'admin_level_2_type' => 'district',
            'admin_level_3_type' => 'upazila',
        ]);
    }

    public function test_admin_can_update_and_delete_company(): void
    {
        $company = Company::create([
            'name' => 'Old Name Corp',
            'slug' => 'old-name-corp',
            'continent' => 'Europe',
            'country' => 'Germany',
            'state' => 'Bavaria',
            'city' => 'Munich',
            'is_active' => true,
        ]);

        $updateRes = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/companies/{$company->id}", [
                'name' => 'Updated Siemens Corp',
                'continent' => 'Europe',
                'country' => 'Germany',
                'state' => 'Bavaria',
                'district' => 'München',
                'city' => 'Munich',
                'market_cap_tier' => 'mega',
            ]);

        $updateRes->assertStatus(200);
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Updated Siemens Corp',
            'district' => 'München',
        ]);

        // Delete
        $delRes = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/companies/{$company->id}");

        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }

    public function test_admin_can_fetch_geo_options(): void
    {
        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/companies/geo-options');

        $res->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'continents',
                'nomenclature' => [
                    'Bangladesh' => ['continent', 'level_1', 'level_2', 'level_3', 'common_divisions'],
                    'United States' => ['continent', 'level_1', 'level_2', 'level_3'],
                ]
            ]);
    }

    public function test_unauthenticated_user_cannot_access_admin_companies(): void
    {
        $this->getJson('/api/v1/admin/companies')->assertStatus(401);
        $this->postJson('/api/v1/admin/companies', ['name' => 'Test'])->assertStatus(401);
    }
}
