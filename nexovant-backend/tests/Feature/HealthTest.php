<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_returns_success_status(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'environment',
                'timestamp',
                'services' => [
                    'database',
                    'queue',
                ],
            ])
            ->assertJson([
                'status' => 'healthy',
                'services' => [
                    'database' => 'healthy',
                ],
            ]);
    }
}
