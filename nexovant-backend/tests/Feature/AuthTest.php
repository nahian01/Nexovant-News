<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_valid_data(): void
    {
        $payload = [
            'name' => 'Alexander Vance',
            'email' => 'alexander.vance@executive.corp',
            'password' => 'ExecutivePass123!',
            'password_confirmation' => 'ExecutivePass123!',
            'account_type' => 'individual',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'account_type',
                    'is_active',
                ],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'alexander.vance@executive.corp',
            'name' => 'Alexander Vance',
            'account_type' => 'individual',
            'is_active' => true,
        ]);
    }

    public function test_registration_fails_with_invalid_data(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'cfo@nexovant.si',
            'password' => 'SecurePass2026!',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'cfo@nexovant.si',
            'password' => 'SecurePass2026!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'email' => 'cfo@nexovant.si',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'user',
                'token',
            ]);
    }

    public function test_user_cannot_login_with_incorrect_password(): void
    {
        User::factory()->create([
            'email' => 'director@nexovant.si',
            'password' => 'CorrectPassword123!',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'director@nexovant.si',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_retrieve_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Elena Rostova',
            'email' => 'elena@intelligence.global',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/user');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => 'Elena Rostova',
                    'email' => 'elena@intelligence.global',
                ],
            ]);
    }

    public function test_unauthenticated_request_to_profile_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/auth/user');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                ],
            ]);
    }

    public function test_user_can_logout_and_revoke_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);
    }
}
