<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Company;
use App\Models\NewsSource;
use App\Models\SocialTrend;
use App\Models\StoryCluster;
use App\Services\Intelligence\TrendingIntelligenceRadarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrendingIntelligenceRadarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create sample company
        $company = Company::create([
            'name' => 'Microsoft Corporation',
            'ticker' => 'MSFT',
            'industry' => 'Artificial Intelligence & Enterprise Software',
            'slug' => 'microsoft-corporation',
            'is_active' => true,
        ]);

        $source = NewsSource::create([
            'name' => 'Financial Times',
            'slug' => 'financial-times',
            'base_url' => 'https://ft.com',
            'feed_url' => 'https://ft.com/rss',
            'credibility_score' => 0.95,
            'trust_tier' => 'tier_1',
            'source_type' => 'wire',
            'is_active' => true,
        ]);

        // Create sample articles
        $article1 = Article::create([
            'source_id' => $source->id,
            'company_id' => $company->id,
            'title' => 'Microsoft calls Copilot "the new OS for work" at Ignite keynote',
            'slug' => 'microsoft-calls-copilot-the-new-os-for-work-at-ignite-keynote-sample',
            'canonical_url' => 'https://ft.com/tech/microsoft-ignite-keynote',
            'content_raw' => '<p>Satya Nadella announced general availability of Office apps in Copilot.</p>',
            'content_normalized' => '<p>Satya Nadella announced general availability of Office apps in Copilot.</p>',
            'importance_score' => 9.2,
            'verification_status' => 'confirmed',
            'status' => 'published',
            'is_breaking' => true,
            'published_at' => now()->subHours(2),
        ]);

        $article2 = Article::create([
            'source_id' => $source->id,
            'title' => 'Waymo temporarily pulls driverless taxis from all US freeways',
            'slug' => 'waymo-temporarily-pulls-driverless-taxis-sample',
            'canonical_url' => 'https://ft.com/tech/waymo-driverless-taxis',
            'content_raw' => '<p>Alphabet autonomous driving unit announces precautionary pause on freeway routes.</p>',
            'content_normalized' => '<p>Alphabet autonomous driving unit announces precautionary pause on freeway routes.</p>',
            'importance_score' => 8.8,
            'verification_status' => 'confirmed',
            'status' => 'published',
            'is_breaking' => false,
            'published_at' => now()->subHours(4),
        ]);

        // Create a story cluster
        StoryCluster::create([
            'title' => 'Microsoft Copilot Enterprise Integration Acceleration',
            'slug' => 'microsoft-copilot-enterprise-integration-acceleration',
            'article_id' => $article1->id,
            'story_count' => 3,
            'confidence_score' => 0.94,
            'status' => 'verified',
            'summary' => 'Surging enterprise interest as Copilot native integration reaches General Availability across Fortune 500 workflows.',
            'created_at' => now()->subHours(3),
        ]);
    }

    public function test_radar_service_detects_and_syncs_trends_from_news_corpus(): void
    {
        $service = app(TrendingIntelligenceRadarService::class);
        $trends = $service->detectAndSyncTrends('24h');

        $this->assertGreaterThanOrEqual(1, $trends->count());

        $first = $trends->first();
        $this->assertNotNull($first->title);
        $this->assertGreaterThan(70, (float) $first->composite_trend_score);
        $this->assertGreaterThan(70, (float) $first->velocity_score);
        $this->assertNotNull($first->summary);
        $this->assertDatabaseHas('social_trends', [
            'id' => $first->id,
        ]);
        $this->assertDatabaseHas('social_trend_metrics', [
            'trend_id' => $first->id,
        ]);
    }

    public function test_trends_index_api_returns_active_radar_list(): void
    {
        // First sync trends
        app(TrendingIntelligenceRadarService::class)->detectAndSyncTrends();

        $response = $this->getJson('/api/v1/trends?time_window=24h');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'status',
                        'composite_trend_score',
                        'velocity_score',
                        'velocity_delta',
                        'popularity_score',
                        'verification_score',
                        'cross_platform_count',
                        'summary',
                        'platform_breakdown',
                        'topic',
                    ],
                ],
                'meta',
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    public function test_trends_scan_endpoint_triggers_on_demand_detection(): void
    {
        $response = $this->postJson('/api/v1/trends/scan', [
            'time_window' => '24h',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'count',
                'data',
            ]);

        $this->assertGreaterThan(0, $response->json('count'));
    }

    public function test_custom_prediction_endpoint_generates_predictive_radar_trend(): void
    {
        $payload = [
            'subject' => 'High-NA EUV Commercial Packaging Challenges',
            'category' => 'technology_adoption',
            'horizon' => '90d',
        ];

        $response = $this->postJson('/api/v1/trends/custom-prediction', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.title', 'Predictive Forecast: High-NA EUV Commercial Packaging Challenges');

        $this->assertDatabaseHas('social_trends', [
            'title' => 'Predictive Forecast: High-NA EUV Commercial Packaging Challenges',
        ]);
    }

    public function test_trend_show_endpoint_returns_deep_dossier(): void
    {
        $service = app(TrendingIntelligenceRadarService::class);
        $trends = $service->detectAndSyncTrends();
        $target = $trends->first();

        $response = $this->getJson("/api/v1/trends/{$target->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $target->id,
                    'title' => $target->title,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title',
                    'status',
                    'composite_trend_score',
                    'velocity_score',
                    'topic',
                    'metrics',
                    'sources',
                ],
            ]);
    }
}
