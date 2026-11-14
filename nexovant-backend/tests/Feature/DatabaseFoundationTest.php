<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AIModel;
use App\Models\AIProvider;
use App\Models\AIProviderCredential;
use App\Models\AITaskConfig;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\Category;
use App\Models\Company;
use App\Models\Notification;
use App\Models\NewsSource;
use App\Models\Permission;
use App\Models\PipelineJob;
use App\Models\Role;
use App\Models\Subcategory;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_user_roles_and_permissions_evaluation(): void
    {
        $admin = User::where('email', 'admin@nexovant.si')->firstOrFail();
        $subscriber = User::where('email', 'executive@enterprise.corp')->firstOrFail();

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertTrue($admin->hasPermission('admin.dashboard.view'));

        $this->assertFalse($subscriber->isAdmin());
        $this->assertTrue($subscriber->hasRole('subscriber'));
        $this->assertFalse($subscriber->hasPermission('admin.users.manage'));
    }

    public function test_ai_provider_credential_eligibility_and_limits(): void
    {
        $gemini = AIProvider::where('slug', 'google-gemini')->firstOrFail();

        $credential = AIProviderCredential::create([
            'provider_id' => $gemini->id,
            'label' => 'Primary Gemini Key',
            'display_name' => 'Gemini 2.0 Production Key #1',
            'encrypted_api_key' => 'encrypted_mock_secret',
            'key_fingerprint' => hash('sha256', 'mock_api_key_123'),
            'status' => 'active',
            'routing_eligibility' => true,
            'daily_request_budget' => 100,
            'current_daily_requests' => 10,
            'is_valid' => true,
        ]);

        $this->assertTrue($credential->isEligible());

        // Saturate request budget
        $credential->update(['current_daily_requests' => 100]);
        $this->assertFalse($credential->isEligible());

        // Reset and suspend
        $credential->update(['current_daily_requests' => 10, 'status' => 'suspended']);
        $this->assertFalse($credential->isEligible());
    }

    public function test_ai_task_configs_registry_seeded(): void
    {
        $summarization = AITaskConfig::where('task_type', 'rss_summarization')->first();
        $this->assertNotNull($summarization);
        $this->assertEquals('RSS Article Summarization', $summarization->display_name);
        $this->assertNotNull($summarization->preferred_provider_id);
        $this->assertNotNull($summarization->preferred_model_id);
        $this->assertIsArray($summarization->fallback_provider_ids);
    }

    public function test_article_manual_override_locks_and_revisions(): void
    {
        $article = Article::where('slug', 'nvidia-custom-silicon-foundry-emea')->firstOrFail();

        $this->assertEquals('PUBLISHED', $article->status);
        $this->assertTrue($article->lock_category);
        $this->assertTrue($article->lock_content);
        $this->assertFalse($article->lock_topic);

        // Assert revision relationship
        $this->assertCount(1, $article->revisions);
        $rev = $article->revisions->first();
        $this->assertEquals(1, $rev->version_number);
        $this->assertEquals($article->title, $rev->title);
    }

    public function test_taxonomy_subcategories_and_topic_hierarchies(): void
    {
        $category = Category::where('slug', 'expansion-capex')->firstOrFail();
        $subcategories = $category->subcategories;

        $this->assertGreaterThan(0, $subcategories->count());
        $this->assertTrue($subcategories->contains('slug', 'foundry-fabs'));

        // Test topic parent/child self-reference
        $parentTopic = Topic::create([
            'category_id' => $category->id,
            'name' => 'Semiconductor Foundry',
            'slug' => 'semiconductor-foundry',
        ]);

        $childTopic = Topic::create([
            'parent_id' => $parentTopic->id,
            'category_id' => $category->id,
            'name' => 'High-NA EUV Lithography',
            'slug' => 'high-na-euv-lithography',
        ]);

        $this->assertEquals($parentTopic->id, $childTopic->parent->id);
        $this->assertTrue($parentTopic->children->contains('id', $childTopic->id));
    }

    public function test_user_saved_articles_and_notifications(): void
    {
        $user = User::where('email', 'executive@enterprise.corp')->firstOrFail();
        $article = Article::where('slug', 'nvidia-custom-silicon-foundry-emea')->firstOrFail();

        // Save article
        $user->savedArticles()->attach($article->id, ['notes' => 'Critical EMEA development']);
        $this->assertTrue($user->savedArticles()->where('articles.id', $article->id)->exists());

        // Notifications
        $notif = Notification::create([
            'user_id' => $user->id,
            'category' => 'breaking',
            'title' => 'Breaking News',
            'message' => 'NVIDIA EMEA silicon capex confirmed.',
            'delivery_channel' => 'in_app',
            'delivery_status' => 'sent',
        ]);

        $this->assertCount(1, $user->notifications()->unread()->get());
        $notif->update(['is_read' => true]);
        $this->assertCount(0, $user->notifications()->unread()->get());
    }

    public function test_pipeline_job_creation_and_stage_tracking(): void
    {
        $source = NewsSource::where('slug', 'sec-edgar-official')->firstOrFail();

        $job = PipelineJob::create([
            'source_id' => $source->id,
            'stage' => '1. Source Discovery',
            'status' => 'completed',
            'item_title' => 'Form 8-K Disclosure',
            'tokens_consumed' => 120,
            'cost_usd' => 0.000045,
            'duration_seconds' => 1.25,
            'started_at' => now()->subSeconds(2),
            'completed_at' => now(),
        ]);

        $this->assertDatabaseHas('pipeline_jobs', [
            'id' => $job->id,
            'status' => 'completed',
            'stage' => '1. Source Discovery',
        ]);
    }
}
