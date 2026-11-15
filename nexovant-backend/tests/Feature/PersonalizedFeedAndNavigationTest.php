<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\FollowedCompany;
use App\Models\NewsSource;
use App\Models\Publication;
use App\Models\SocialTrend;
use App\Models\SubscriberMonitor;
use App\Models\SubscriberWatchlist;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalizedFeedAndNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected User $subscriber;
    protected NewsSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriber = User::factory()->create([
            'email' => 'subscriber-stage8@nexovant.test',
            'account_type' => 'subscriber',
        ]);

        $this->source = NewsSource::create([
            'name' => 'Reuters Technology',
            'url' => 'https://reuters.com/tech',
            'source_type' => 'rss',
            'credibility_score' => 9.5,
            'is_active' => true,
        ]);
    }

    public function test_authenticated_subscriber_can_fetch_personalized_feed_stream(): void
    {
        $article = Article::create([
            'title' => 'ASML Ships Next-Gen High-NA EUV Scanner to TSMC Foundry Hub',
            'slug' => 'asml-ships-high-na-euv-tsmc',
            'canonical_url' => 'https://reuters.com/tech/asml-ships-high-na-euv-tsmc',
            'content_raw' => 'ASML Holding NV has completed initial transport of its flagship extreme ultraviolet lithography system...',
            'content_normalized' => 'ASML Holding NV has completed transport of its flagship EUV scanner...',
            'source_id' => $this->source->id,
            'verification_status' => 'confirmed',
            'importance_score' => 9.2,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($this->subscriber, 'sanctum')
            ->getJson('/api/v1/workspace/feed');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'feed' => ['for_you'],
                'data' => [
                    '*' => [
                        'id',
                        'headline',
                        'summary',
                        'verification_status',
                        'relevance_score',
                        'reading_time_minutes',
                        'source' => ['name', 'credibility_score'],
                        'is_saved',
                        'is_following',
                    ]
                ],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_unauthenticated_user_cannot_access_subscriber_feed(): void
    {
        $response = $this->getJson('/api/v1/workspace/feed');
        $response->assertStatus(401);
    }

    public function test_feed_filters_by_category(): void
    {
        $categorySemi = Category::create([
            'name' => 'Semiconductors',
            'slug' => 'semiconductors',
            'color' => '#147A67',
            'is_active' => true,
        ]);

        $categoryCloud = Category::create([
            'name' => 'Cloud Infrastructure',
            'slug' => 'cloud-infrastructure',
            'color' => '#2563EB',
            'is_active' => true,
        ]);

        $articleA = Article::create([
            'title' => 'TSMC Expands Sub-2nm Lithography Facility in Dresden',
            'slug' => 'tsmc-dresden-fab-expansion',
            'canonical_url' => 'https://reuters.com/tech/tsmc-dresden-fab-expansion',
            'content_raw' => 'Taiwan Semiconductor Manufacturing Co begins construction...',
            'content_normalized' => 'Taiwan Semiconductor Manufacturing Co begins construction on Dresden facility...',
            'source_id' => $this->source->id,
            'verification_status' => 'confirmed',
            'importance_score' => 8.5,
            'published_at' => now(),
        ]);
        $articleA->categories()->sync([$categorySemi->id]);

        $articleB = Article::create([
            'title' => 'AWS Launches Dedicated Hyperscale Data Center Region in Oslo',
            'slug' => 'aws-hyperscale-oslo-region',
            'canonical_url' => 'https://reuters.com/tech/aws-hyperscale-oslo-region',
            'content_raw' => 'Amazon Web Services expands European cloud compute footprint...',
            'content_normalized' => 'Amazon Web Services expands European cloud compute footprint...',
            'source_id' => $this->source->id,
            'verification_status' => 'confirmed',
            'importance_score' => 7.5,
            'published_at' => now(),
        ]);
        $articleB->categories()->sync([$categoryCloud->id]);

        // Filter by semiconductors
        $response = $this->actingAs($this->subscriber, 'sanctum')
            ->getJson('/api/v1/workspace/feed?category=semiconductors');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($articleA->id, $data[0]['id']);
    }

    public function test_feed_sorts_by_latest_and_credibility(): void
    {
        $articleOlder = Article::create([
            'title' => 'Early Signal: Quantum Computing Milestone',
            'slug' => 'early-quantum-milestone',
            'canonical_url' => 'https://reuters.com/tech/early-quantum-milestone',
            'content_raw' => 'Developing quantum research report...',
            'content_normalized' => 'Developing quantum research report...',
            'source_id' => $this->source->id,
            'verification_status' => 'reported_reliable',
            'importance_score' => 9.0,
            'published_at' => now()->subDays(3),
        ]);

        $articleNewer = Article::create([
            'title' => 'Latest Breaking News: Microcontroller Shortage Resolved',
            'slug' => 'microcontroller-shortage-resolved',
            'canonical_url' => 'https://reuters.com/tech/microcontroller-shortage-resolved',
            'content_raw' => 'Global automotive component shortage officially resolved...',
            'content_normalized' => 'Global automotive component shortage officially resolved...',
            'source_id' => $this->source->id,
            'verification_status' => 'confirmed',
            'importance_score' => 8.0,
            'published_at' => now()->subMinutes(10),
        ]);

        // Test Latest sort
        $latestRes = $this->actingAs($this->subscriber, 'sanctum')
            ->getJson('/api/v1/workspace/feed?sort=latest');

        $latestRes->assertStatus(200);
        $items = $latestRes->json('data');
        $this->assertNotEmpty($items);
        $this->assertEquals($articleNewer->id, $items[0]['id']);

        // Test Credible sort
        $credRes = $this->actingAs($this->subscriber, 'sanctum')
            ->getJson('/api/v1/workspace/feed?sort=credible');
        $credRes->assertStatus(200);
        $this->assertEquals('credible', $credRes->json('stream.filters.sort'));
    }

    public function test_subscriber_overview_returns_real_metrics(): void
    {
        // 1. Create 2 watchlists
        $wl1 = SubscriberWatchlist::create([
            'user_id' => $this->subscriber->id,
            'name' => 'Semiconductor Heavyweights',
            'is_default' => true,
        ]);
        $wl1->items()->create(['entity_name' => 'NVIDIA Corp', 'entity_type' => 'company']);
        $wl1->items()->create(['entity_name' => 'TSMC', 'entity_type' => 'company']);

        // 2. Create 1 active monitor
        SubscriberMonitor::create([
            'user_id' => $this->subscriber->id,
            'name' => 'EUV Supply Disruption',
            'entity_type' => 'technology',
            'entity_name' => 'EUV Lithography',
            'keywords' => ['EUV', 'lithography', 'supply'],
            'is_active' => true,
        ]);

        // 3. Save 1 item to personal library
        $this->subscriber->libraryItems()->create([
            'item_type' => 'article',
            'item_id' => 'art-test-01',
            'private_notes' => 'Key procurement benchmark',
        ]);

        $response = $this->actingAs($this->subscriber, 'sanctum')
            ->getJson('/api/v1/workspace/overview');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'overview' => [
                    'metrics' => [
                        'active_watchlists' => 1,
                        'active_monitors' => 1,
                        'saved_intelligence' => 1,
                    ],
                ]
            ]);

        $this->assertGreaterThanOrEqual(2, $response->json('overview.metrics.followed_entities'));
        $this->assertNotEmpty($response->json('overview.greeting'));
        $this->assertNotEmpty($response->json('overview.summary'));
    }

    public function test_following_management_endpoints(): void
    {
        $company = Company::create([
            'name' => 'Arm Holdings PLC',
            'slug' => 'arm-holdings',
            'ticker' => 'ARM',
            'is_active' => true,
        ]);

        // 1. Initially user does not follow Arm
        $listRes = $this->actingAs($this->subscriber, 'sanctum')
            ->getJson('/api/v1/workspace/following');
        $listRes->assertStatus(200);

        // 2. Toggle follow Arm Holdings
        $toggleRes = $this->actingAs($this->subscriber, 'sanctum')
            ->postJson('/api/v1/workspace/following/toggle', [
                'entity_type' => 'company',
                'entity_id' => $company->id,
                'name' => $company->name,
            ]);

        $toggleRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_followed' => true,
            ]);

        $this->assertDatabaseHas('followed_companies', [
            'user_id' => $this->subscriber->id,
            'company_id' => $company->id,
        ]);

        // 3. Following list now includes Arm Holdings
        $followingList = $this->actingAs($this->subscriber, 'sanctum')
            ->getJson('/api/v1/workspace/following');
        $this->assertEquals(1, $followingList->json('total'));
        $this->assertEquals('Arm Holdings PLC', $followingList->json('following.0.name'));

        // 4. Following suggestions
        $suggRes = $this->actingAs($this->subscriber, 'sanctum')
            ->getJson('/api/v1/workspace/following/suggestions');
        $suggRes->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'suggestions' => ['companies', 'technologies'],
            ]);

        // 5. Unfollow Arm Holdings
        $unfollowRes = $this->actingAs($this->subscriber, 'sanctum')
            ->postJson('/api/v1/workspace/following/toggle', [
                'entity_type' => 'company',
                'entity_id' => $company->id,
            ]);

        $unfollowRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_followed' => false,
            ]);

        $this->assertDatabaseMissing('followed_companies', [
            'user_id' => $this->subscriber->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_feed_card_interaction_recording(): void
    {
        $response = $this->actingAs($this->subscriber, 'sanctum')
            ->postJson('/api/v1/workspace/interact', [
                'action' => 'save',
                'target_type' => 'article',
                'target_id' => 'art-12345',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Interaction recorded.',
            ]);

        $this->assertDatabaseHas('subscriber_interactions', [
            'user_id' => $this->subscriber->id,
            'interaction_type' => 'save',
            'target_id' => 'art-12345',
        ]);
    }

    public function test_search_endpoint_returns_multi_entity_results(): void
    {
        Article::create([
            'title' => 'IBM Demonstrates 1,121-Qubit Quantum Condor Processor',
            'slug' => 'ibm-quantum-condor-processor',
            'canonical_url' => 'https://reuters.com/tech/ibm-quantum-condor',
            'content_raw' => 'IBM researchers unveil quantum computing breakthrough...',
            'content_normalized' => 'IBM researchers unveil quantum computing breakthrough...',
            'source_id' => $this->source->id,
            'verification_status' => 'confirmed',
            'published_at' => now(),
        ]);

        Technology::create([
            'name' => 'Quantum Annealing & Error Correction',
            'slug' => 'quantum-annealing',
            'category' => 'Emerging Compute',
            'description' => 'Fault-tolerant quantum architecture',
            'is_active' => true,
        ]);

        Publication::create([
            'title' => 'Quantum Supremacy & Enterprise Cryptography Risk',
            'subtitle' => 'Special Executive Dossier on Post-Quantum Security',
            'edition_number' => 'ED-2026-Q1',
            'publication_type' => 'executive_dossier',
            'file_path' => 'publications/ed-2026-q1.pdf',
            'status' => 'published',
            'publication_date' => now(),
        ]);

        $response = $this->getJson('/api/v1/search?q=Quantum');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'query',
                'data' => [
                    'articles',
                    'companies',
                    'executives',
                    'technologies',
                    'reports',
                    'trends',
                ]
            ]);

        $this->assertNotEmpty($response->json('data.articles'));
        $this->assertNotEmpty($response->json('data.technologies'));
        $this->assertNotEmpty($response->json('data.reports'));
    }

    public function test_trends_endpoint_supports_time_window_filtering(): void
    {
        $topic = \App\Models\SocialTopic::create([
            'title' => 'High-NA EUV Lithography',
            'slug' => 'high-na-euv-lithography',
        ]);

        SocialTrend::create([
            'topic_id' => $topic->id,
            'title' => 'High-NA EUV Adoption Velocity',
            'status' => 'active',
            'composite_trend_score' => 95.4,
            'first_detected_at' => now(),
        ]);

        $res24h = $this->getJson('/api/v1/trends?time_window=24h');
        $res24h->assertStatus(200)
            ->assertJson(['success' => true]);

        $res7d = $this->getJson('/api/v1/trends?time_window=7d');
        $res7d->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
