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

    public function test_admin_can_request_mfa_code_and_login_without_database_password(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $admin = User::where('email', 'admin@nexovant.si')->firstOrFail();
        // Scramble database password to demonstrate zero DB password dependency
        $admin->update(['password' => '!LOCKED_ZERO_DB_PASSWORD_STORAGE!']);

        $requestResponse = $this->postJson('/api/v1/auth/admin/request-code', [
            'email' => 'admin@nexovant.si',
        ]);

        $requestResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\AdminMfaLoginMail::class, function ($mail) {
            return $mail->hasTo('abdullahalnahian0@gmail.com');
        });

        // Set known MFA code in cache
        $mfaCode = '749102';
        \Illuminate\Support\Facades\Cache::put('admin_mfa_code_admin@nexovant.si', \Illuminate\Support\Facades\Hash::make($mfaCode), now()->addMinutes(10));

        $loginResponse = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin@nexovant.si',
            'password' => $mfaCode,
        ]);

        $loginResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'mfa_verified' => true,
                'user' => [
                    'email' => 'admin@nexovant.si',
                    'is_admin' => true,
                ]
            ]);

        // Verify single-use: cached code must be purged immediately
        $this->assertNull(\Illuminate\Support\Facades\Cache::get('admin_mfa_code_admin@nexovant.si'));
    }
}
