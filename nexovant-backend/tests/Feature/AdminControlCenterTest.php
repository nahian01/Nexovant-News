<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AIProvider;
use App\Models\AIProviderCredential;
use App\Models\Article;
use App\Models\Category;
use App\Models\NewsSource;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class AdminControlCenterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@nexovant.si')->firstOrFail();
    }

    public function test_admin_dashboard_metrics_and_trigger_scan(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'overview' => [
                    'total_users',
                    'active_users',
                    'published_stories',
                    'active_ai_providers',
                    'available_ai_credentials',
                    'system_health',
                ],
                'health_probes' => [
                    'database',
                    'cache',
                    'ai_gateway',
                ],
                'recent_activity',
            ]);

        // Test operational scan trigger
        $scanResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/dashboard/scan');

        $scanResponse->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_ai_credential_registration_encryption_and_duplicate_detection(): void
    {
        $provider = AIProvider::firstOrFail();

        // 1. Register Credential
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/ai-credentials', [
                'provider_id' => $provider->id,
                'display_name' => 'Claude 3.5 Production Vault #1',
                'api_key' => 'sk-ant-api03-live-test-super-secret-key-998877',
                'priority' => 1,
                'daily_request_budget' => 500,
                'monthly_spending_budget' => 200.00,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'credential' => [
                    'display_name' => 'Claude 3.5 Production Vault #1',
                    'masked_key' => 'sk-ant-...8877',
                ]
            ]);

        $credId = $response->json('credential.id');
        $saved = AIProviderCredential::findOrFail($credId);

        // Verify encrypted at rest
        $this->assertNotEquals('sk-ant-api03-live-test-super-secret-key-998877', $saved->encrypted_api_key);
        $this->assertEquals('sk-ant-api03-live-test-super-secret-key-998877', Crypt::decryptString($saved->encrypted_api_key));

        // 2. Reject duplicate API key
        $dupResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/ai-credentials', [
                'provider_id' => $provider->id,
                'display_name' => 'Duplicate Key Attempt',
                'api_key' => 'sk-ant-api03-live-test-super-secret-key-998877',
            ]);

        $dupResponse->assertStatus(422)
            ->assertJson(['error' => ['code' => 'DUPLICATE_CREDENTIAL']]);

        // 3. Test credential probe
        $testResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/ai-credentials/{$credId}/test");

        $testResponse->assertStatus(200)
            ->assertJson(['success' => true, 'status' => 'healthy']);
    }

    public function test_article_crud_with_manual_locks_and_revision_restoration(): void
    {
        // 1. Create Article with 5 locks
        $createResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/articles', [
                'title' => 'Quantum Computing Infrastructure Expansion In Europe',
                'content_raw' => 'Initial raw disclosure draft regarding European quantum accelerator deployment.',
                'status' => 'REVIEW',
                'lock_category' => true,
                'lock_topic' => true,
                'lock_content' => true,
                'lock_image' => true,
                'lock_tags' => true,
                'what_happened' => 'Quantum compute cluster authorized.',
            ]);

        $createResponse->assertStatus(201);
        $articleId = $createResponse->json('article.id');

        $article = Article::findOrFail($articleId);
        $this->assertTrue($article->lock_category);
        $this->assertTrue($article->lock_content);
        $this->assertEquals('REVIEW', $article->status);

        // 2. Update Article and check revision created
        $updateResponse = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/articles/{$articleId}", [
                'content_raw' => 'Updated raw content with confirmed EU Chips Act subsidies.',
                'status' => 'PUBLISHED',
                'is_editorial_approved' => true,
                'change_summary' => 'Added subsidy confirmation.',
            ]);

        $updateResponse->assertStatus(200);

        // Assert 2 revisions exist
        $revsResponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/articles/{$articleId}/revisions");

        $revsResponse->assertStatus(200);
        $this->assertCount(2, $revsResponse->json('data'));

        // 3. Restore Revision 1
        $restoreResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/articles/{$articleId}/revisions/1/restore");

        $restoreResponse->assertStatus(200);
        $this->assertDatabaseHas('articles', [
            'id' => $articleId,
            'content_raw' => 'Initial raw disclosure draft regarding European quantum accelerator deployment.',
        ]);
    }

    public function test_system_settings_persistent_update(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/admin/system/settings', [
                'breaking_news_importance_threshold' => 9.5,
                'require_mfa_for_admins' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'settings' => [
                    'breaking_news_importance_threshold' => 9.5,
                ]
            ]);

        // Read back
        $readResponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/system/settings');

        $readResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'settings' => [
                    'breaking_news_importance_threshold' => 9.5,
                ]
            ]);
    }
}
