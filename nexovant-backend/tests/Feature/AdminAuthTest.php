<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        $subscriber = User::where('email', 'executive@enterprise.corp')->firstOrFail();

        $response = $this->actingAs($subscriber, 'sanctum')
            ->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN_ADMIN_ACCESS',
                ]
            ]);
    }

    public function test_admin_can_login_via_admin_login_endpoint(): void
    {
        $admin = User::where('email', 'admin@nexovant.si')->firstOrFail();
        $admin->update(['password' => \Illuminate\Support\Facades\Hash::make('TestFixtureAuthPass123!')]);

        $response = $this->postJson('/api/v1/auth/admin-login', [
            'email' => 'admin@nexovant.si',
            'password' => 'TestFixtureAuthPass123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'email' => 'admin@nexovant.si',
                    'is_admin' => true,
                ]
            ])
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_non_admin_is_rejected_at_admin_login(): void
    {
        $subscriber = User::where('email', 'executive@enterprise.corp')->firstOrFail();
        $subscriber->update(['password' => \Illuminate\Support\Facades\Hash::make('TestFixtureAuthPass123!')]);

        $response = $this->postJson('/api/v1/auth/admin-login', [
            'email' => 'executive@enterprise.corp',
            'password' => 'TestFixtureAuthPass123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN_NOT_ADMIN',
                ]
            ]);
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = User::where('email', 'admin@nexovant.si')->firstOrFail();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
