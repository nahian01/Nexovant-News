<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\DiscoveredStory;
use App\Models\NewsSource;
use App\Models\StoryCluster;
use App\Models\Subcategory;
use App\Models\SubscriberNotificationPreference;
use App\Models\Topic;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Ingestion\IntelligencePipelineCoordinator;
use App\Services\Taxonomy\AutoTaggingService;
use App\Services\Workspace\PersonalizedFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Stage15FeedPersonalizationAndNotificationChannelsTest extends TestCase
{
    use RefreshDatabase;

    protected function createNewsSource(string $name = 'Reuters Tech Wire'): NewsSource
    {
        return NewsSource::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'url' => 'https://news.example.com/' . Str::slug($name),
            'feed_url' => 'https://news.example.com/' . Str::slug($name) . '/rss',
            'is_active' => true,
            'credibility_score' => 9.2,
        ]);
    }

    protected function createArticle(array $overrides = []): Article
    {
        $title = $overrides['title'] ?? 'Strategic Intelligence Report ' . Str::random(5);
        $slug = $overrides['slug'] ?? Str::slug($title) . '-' . Str::random(4);
        $content = $overrides['content_normalized'] ?? ($overrides['content_raw'] ?? 'Synthesized intelligence content for institutional subscriber briefing.');

        return Article::create(array_merge([
            'id' => (string) Str::uuid(),
            'source_id' => $overrides['source_id'] ?? $this->createNewsSource()->id,
            'title' => $title,
            'slug' => $slug,
            'canonical_url' => 'https://news.nexovant.test/articles/' . $slug,
            'summary' => substr($content, 0, 150),
            'content_raw' => $content,
            'content_normalized' => $content,
            'content_hash' => hash('sha256', $title . '|' . $content),
            'published_at' => now(),
            'verification_status' => 'confirmed',
            'importance_score' => 8.0,
            'status' => 'PUBLISHED',
            'is_editorial_approved' => true,
        ], $overrides));
    }

    /**
     * Test 1: AutoTagging classifies into Industry, Category, and Subcategory.
     */
    public function test_auto_tagging_extracts_industry_category_and_subcategory(): void
    {
        /** @var AutoTaggingService $service */
        $service = app(AutoTaggingService::class);

        $text = "TSMC and ASML announce breakthrough High-NA EUV lithography foundry installation for 2nm semiconductor wafer manufacturing.";
        $result = $service->classifyStory($text);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result['industry']);
        $this->assertEquals('Technology', $result['industry']);
        $this->assertEquals('semiconductors', $result['category_slug']);
        $this->assertNotEmpty($result['subcategory_slug']);
        $this->assertIsArray($result['topics']);
        $this->assertIsArray($result['tags']);
    }

    /**
     * Test 2: Ingestion pipeline dynamically creates Category and Subcategory and attaches them to synthesized Article.
     */
    public function test_ingestion_pipeline_dynamically_creates_category_and_subcategory(): void
    {
        $source = $this->createNewsSource('CleanTech Wire');
        $story = DiscoveredStory::create([
            'source_id' => $source->id,
            'title' => 'Breakthrough in Solid-State Battery Storage for CleanTech Grid',
            'canonical_url' => 'https://reuters.com/clean-energy-' . Str::random(6),
            'summary' => 'Engineers demonstrate high-capacity solid-state lithium battery for grid modernization and renewable energy storage.',
            'content_raw' => 'Engineers demonstrate high-capacity solid-state lithium battery for grid modernization and renewable energy storage.',
            'content_normalized' => 'Engineers demonstrate high-capacity solid-state lithium battery for grid modernization and renewable energy storage.',
            'status' => 'extracted',
            'published_at' => now(),
        ]);

        $cluster = StoryCluster::create([
            'title' => 'Solid-State Battery Storage for CleanTech Grid',
            'summary' => 'Engineers demonstrate high-capacity solid-state lithium battery for grid modernization.',
            'status' => 'formed',
        ]);
        $cluster->stories()->attach($story->id, ['is_primary' => true, 'similarity_score' => 0.95]);

        /** @var IntelligencePipelineCoordinator $coordinator */
        $coordinator = app(IntelligencePipelineCoordinator::class);
        $article = $coordinator->processClusterPipeline($cluster);

        $this->assertNotNull($article);
        $this->assertTrue($article->categories()->exists());
        $this->assertTrue($article->subcategories()->exists());

        $category = $article->categories()->first();
        $subcategory = $article->subcategories()->first();

        $this->assertEquals($category->id, $subcategory->category_id);
    }

    /**
     * Test 3: PersonalizedFeedService enforces strict user profile grounding for "For You".
     */
    public function test_for_you_section_is_strictly_grounded_in_user_profile(): void
    {
        $user = User::factory()->create();
        UserPreference::create([
            'user_id' => $user->id,
            'preferred_topics' => ['Lithography & Fabs'],
            'preferred_industries' => ['Semiconductors'],
            'personalization_enabled' => true,
        ]);

        $company = Company::create([
            'id' => (string) Str::uuid(),
            'name' => 'ASML Holding',
            'slug' => 'asml-holding',
            'ticker' => 'ASML',
            'sector' => 'Technology',
            'country' => 'Netherlands',
            'is_tracked' => true,
        ]);
        $user->followedCompanies()->create(['company_id' => $company->id]);

        $matchedArticle = $this->createArticle([
            'title' => 'ASML ships next-generation Twinscan High-NA EUV lithography tool',
            'content_normalized' => 'ASML has finalized delivery of its flagship EUV scanner.',
            'verification_status' => 'confirmed',
            'importance_score' => 9.0,
            'published_at' => now(),
        ]);
        $matchedArticle->companies()->attach($company->id);

        $unrelatedArticle = $this->createArticle([
            'title' => 'Local municipal transit upgrades ticketing kiosks',
            'content_normalized' => 'The regional transportation office upgraded legacy kiosks.',
            'verification_status' => 'confirmed',
            'importance_score' => 3.0,
            'published_at' => now()->subDay(),
        ]);

        /** @var PersonalizedFeedService $feedService */
        $feedService = app(PersonalizedFeedService::class);
        $feed = $feedService->getPersonalizedFeed($user);

        $this->assertArrayHasKey('for_you', $feed);
        $forYouItems = $feed['for_you'];

        $this->assertNotEmpty($forYouItems);
        $this->assertEquals($matchedArticle->id, $forYouItems[0]['id']);
        $this->assertStringContainsString('ASML', $forYouItems[0]['personalization_explanation']);
    }

    /**
     * Test 4: Overview Feed applies the 80% user interest / 20% diverse discovery ratio.
     */
    public function test_overview_feed_stream_applies_80_20_interest_and_diverse_ratio(): void
    {
        $user = User::factory()->create();
        $semiconductorCategory = Category::create([
            'name' => 'Semiconductors',
            'slug' => 'semiconductors',
            'color' => '#147A67',
            'is_active' => true,
        ]);
        $energyCategory = Category::create([
            'name' => 'Energy & Sustainability',
            'slug' => 'energy-sustainability',
            'color' => '#8A5A08',
            'is_active' => true,
        ]);

        UserPreference::create([
            'user_id' => $user->id,
            'preferred_industries' => ['Semiconductors'],
            'preferred_topics' => ['Semiconductors', 'Lithography'],
            'personalization_enabled' => true,
        ]);

        // Create 8 semiconductor articles (interest pool)
        for ($i = 1; $i <= 8; $i++) {
            $art = $this->createArticle([
                'title' => "Advanced Semiconductor Wafer Processing Volume {$i}",
                'content_normalized' => "Details on semiconductor foundry lithography nodes for iteration {$i}",
                'verification_status' => 'confirmed',
                'importance_score' => 8.0,
                'published_at' => now()->subMinutes($i * 5),
            ]);
            $art->categories()->attach($semiconductorCategory->id);
        }

        // Create 4 clean energy articles (diverse pool)
        for ($j = 1; $j <= 4; $j++) {
            $art = $this->createArticle([
                'title' => "Grid Scale Hydrogen Fuel Cell Deployment Wave {$j}",
                'content_normalized' => "Global energy storage infrastructure expansion wave {$j}",
                'verification_status' => 'confirmed',
                'importance_score' => 7.5,
                'published_at' => now()->subMinutes($j * 10),
            ]);
            $art->categories()->attach($energyCategory->id);
        }

        /** @var PersonalizedFeedService $feedService */
        $feedService = app(PersonalizedFeedService::class);
        $stream = $feedService->getFeedStream($user, ['per_page' => 10]);

        $this->assertNotEmpty($stream['items']);
        $this->assertArrayHasKey('personalization_distribution', $stream['meta']);
        $this->assertEquals(80, $stream['meta']['personalization_distribution']['interest_percentage']);
        $this->assertEquals(20, $stream['meta']['personalization_distribution']['diversity_percentage']);

        $interestItems = array_filter($stream['items'], fn ($it) => ($it['match_type'] ?? '') === 'interest');
        $diverseItems = array_filter($stream['items'], fn ($it) => ($it['match_type'] ?? '') === 'diverse');

        $this->assertNotEmpty($interestItems);
        $this->assertNotEmpty($diverseItems);
    }

    /**
     * Test 5: Telegram and WhatsApp connection and management endpoints.
     */
    public function test_telegram_and_whatsapp_connection_flow(): void
    {
        $user = User::factory()->create();

        // 1. Connect Telegram
        $response = $this->actingAs($user)->postJson('/api/v1/workspace/notifications/telegram/connect', [
            'chat_id' => '@intel_analyst_2026',
        ]);
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('chat_id', 'intel_analyst_2026');

        $pref = SubscriberNotificationPreference::where('user_id', $user->id)->first();
        $this->assertEquals('intel_analyst_2026', $pref->telegram_chat_id);
        $this->assertTrue($pref->channels['telegram']);

        // 2. Disconnect Telegram
        $discResponse = $this->actingAs($user)->postJson('/api/v1/workspace/notifications/telegram/disconnect');
        $discResponse->assertOk()->assertJsonPath('success', true);

        $pref->refresh();
        $this->assertNull($pref->telegram_chat_id);
        $this->assertFalse($pref->channels['telegram']);

        // 3. Connect WhatsApp with validation
        $invalidWhatsapp = $this->actingAs($user)->postJson('/api/v1/workspace/notifications/whatsapp/connect', [
            'phone_number' => '1234',
        ]);
        $invalidWhatsapp->assertStatus(422);

        $validWhatsapp = $this->actingAs($user)->postJson('/api/v1/workspace/notifications/whatsapp/connect', [
            'phone_number' => '+1 (415) 555-2671',
        ]);
        $validWhatsapp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('phone_number', '+14155552671');

        $pref->refresh();
        $this->assertEquals('+14155552671', $pref->whatsapp_phone);
        $this->assertTrue($pref->channels['whatsapp']);

        // 4. Test WhatsApp
        $testWhatsapp = $this->actingAs($user)->postJson('/api/v1/workspace/notifications/whatsapp/test');
        $testWhatsapp->assertOk()
            ->assertJsonPath('phone_number', '+14155552671');

        // 5. Disconnect WhatsApp
        $discWhatsapp = $this->actingAs($user)->postJson('/api/v1/workspace/notifications/whatsapp/disconnect');
        $discWhatsapp->assertOk()->assertJsonPath('success', true);

        $pref->refresh();
        $this->assertNull($pref->whatsapp_phone);
        $this->assertFalse($pref->channels['whatsapp']);
    }
}
