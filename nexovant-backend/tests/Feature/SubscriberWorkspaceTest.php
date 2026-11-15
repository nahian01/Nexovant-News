<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Company;
use App\Models\IntelligenceEntity;
use App\Models\IntelligenceEvent;
use App\Models\NewsSource;
use App\Models\Notification;
use App\Models\NotificationDeliveryLog;
use App\Models\Plan;
use App\Models\Publication;
use App\Models\Role;
use App\Models\SubscriberCollection;
use App\Models\SubscriberLibraryItem;
use App\Models\SubscriberMonitor;
use App\Models\SubscriberWatchlist;
use App\Models\SubscriberWatchlistItem;
use App\Models\User;
use App\Services\Workspace\IntelligenceMonitorService;
use App\Services\Workspace\PersonalLibraryService;
use App\Services\Workspace\PersonalizedFeedService;
use App\Services\Workspace\SubscriberNotificationService;
use App\Services\Workspace\SubscriberResearchService;
use App\Services\Workspace\WatchlistService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriberWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $subscriberA;
    protected User $subscriberB;
    protected Article $sampleArticle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->adminUser = User::where('email', 'admin@nexovant.si')->firstOrFail();

        $subscriberRole = Role::where('name', 'subscriber')->first();

        $this->subscriberA = User::factory()->create([
            'email' => 'subscriber.a@nexovant.si',
            'account_type' => 'individual',
        ]);
        if ($subscriberRole) {
            $this->subscriberA->roles()->attach($subscriberRole->id);
        }

        $this->subscriberB = User::factory()->create([
            'email' => 'subscriber.b@nexovant.si',
            'account_type' => 'individual',
        ]);
        if ($subscriberRole) {
            $this->subscriberB->roles()->attach($subscriberRole->id);
        }

        $source = NewsSource::firstOrCreate(
            ['slug' => 'reuters-wire-hub'],
            [
                'name' => 'Reuters Global Wire',
                'url' => 'https://reuters.com/tech',
                'feed_url' => 'https://reuters.com/tech/rss',
                'source_type' => 'wire',
                'is_active' => true,
            ]
        );

        $this->sampleArticle = Article::firstOrCreate(
            ['slug' => 'tsmc-dresden-fab-joint-venture-breakthrough'],
            [
                'title' => 'TSMC and ASML Finalize European Fab Tooling Integration',
                'source_id' => $source->id,
                'canonical_url' => 'https://reuters.com/tech/tsmc-asml-european-fab',
                'content_raw' => 'TSMC and ASML confirm High-NA EUV lithography tool delivery to European joint venture cleanroom in Dresden.',
                'content_normalized' => 'TSMC and ASML confirm High-NA EUV lithography tool delivery to European joint venture cleanroom in Dresden.',
                'published_at' => now(),
                'verification_status' => 'verified',
                'importance_score' => 9.2,
                'is_breaking' => true,
            ]
        );

        Company::firstOrCreate(
            ['ticker' => 'TSMC'],
            ['name' => 'Taiwan Semiconductor Manufacturing Co.', 'slug' => 'tsmc', 'sector' => 'Foundry']
        );
        Company::firstOrCreate(
            ['ticker' => 'ASML'],
            ['name' => 'ASML Holding N.V.', 'slug' => 'asml', 'sector' => 'Semiconductor Equipment']
        );
    }

    public function test_personalized_feed_relevance_and_explanations(): void
    {
        // 1. Subscriber A follows ASML via watchlist
        $wlService = app(WatchlistService::class);
        $watchlist = $wlService->createWatchlist($this->subscriberA, [
            'name' => 'Lithography Equipment',
            'is_default' => true,
        ]);
        $wlService->addItem($this->subscriberA, $watchlist->id, [
            'entity_name' => 'ASML',
            'entity_type' => 'company',
        ]);

        // 2. Fetch personalized feed
        $res = $this->actingAs($this->subscriberA, 'sanctum')->getJson('/api/v1/workspace/feed');
        $res->assertOk();
        $res->assertJsonStructure([
            'success',
            'feed' => [
                'for_you',
                'following',
                'breaking',
                'trending',
                'companies',
                'technologies',
                'industries',
                'saved',
                'recommended_research',
            ],
        ]);

        $forYou = $res->json('feed.for_you');
        $this->assertNotEmpty($forYou);

        // Verify transparent attribution
        $firstItem = $forYou[0];
        $this->assertArrayHasKey('personalization_explanation', $firstItem);
        $this->assertStringContainsString('ASML', $firstItem['personalization_explanation']);
    }

    public function test_watchlist_crud_ownership_and_duplicate_prevention(): void
    {
        // 1. Create Watchlist
        $createRes = $this->actingAs($this->subscriberA, 'sanctum')->postJson('/api/v1/workspace/watchlists', [
            'name' => 'Global Foundries Watchlist',
            'description' => 'Monitoring tier-1 pure-play silicon foundries.',
            'notification_frequency' => 'daily',
        ]);
        $createRes->assertCreated();
        $watchlistId = $createRes->json('watchlist.id');

        // 2. Add Entity Item
        $addItemRes = $this->actingAs($this->subscriberA, 'sanctum')->postJson("/api/v1/workspace/watchlists/{$watchlistId}/items", [
            'entity_name' => 'TSMC',
            'entity_type' => 'company',
        ]);
        $addItemRes->assertCreated();

        // 3. Duplicate Prevention: Adding TSMC again returns existing record without throwing duplicate constraint
        $addDuplicateRes = $this->actingAs($this->subscriberA, 'sanctum')->postJson("/api/v1/workspace/watchlists/{$watchlistId}/items", [
            'entity_name' => 'TSMC',
            'entity_type' => 'company',
        ]);
        $addDuplicateRes->assertSuccessful();
        $this->assertEquals(1, SubscriberWatchlistItem::where('watchlist_id', $watchlistId)->count());

        // 4. Cross-User Privacy: Subscriber B cannot view or modify Subscriber A's watchlist
        $unauthorizedShow = $this->actingAs($this->subscriberB, 'sanctum')->getJson("/api/v1/workspace/watchlists/{$watchlistId}");
        $unauthorizedShow->assertNotFound();

        $unauthorizedDelete = $this->actingAs($this->subscriberB, 'sanctum')->deleteJson("/api/v1/workspace/watchlists/{$watchlistId}");
        $unauthorizedDelete->assertNotFound();
    }

    public function test_custom_monitor_creation_and_matching_alert_dispatch(): void
    {
        $monitorService = app(IntelligenceMonitorService::class);

        // 1. Create Monitor with keywords and exclusions
        $createRes = $this->actingAs($this->subscriberA, 'sanctum')->postJson('/api/v1/workspace/monitors', [
            'name' => 'High-NA EUV Alert Rule',
            'keywords' => ['lithography', 'High-NA'],
            'excluded_terms' => ['unconfirmed rumor'],
            'min_credibility_score' => 0.80,
            'require_verified_only' => true,
            'alert_frequency' => 'real_time',
            'delivery_channels' => ['in_app', 'email'],
        ]);
        $createRes->assertCreated();
        $monitorId = $createRes->json('monitor.id');

        // 2. Preview matches
        $previewRes = $this->actingAs($this->subscriberA, 'sanctum')->postJson('/api/v1/workspace/monitors/preview', [
            'keywords' => ['lithography', 'High-NA'],
            'require_verified_only' => true,
        ]);
        $previewRes->assertOk();
        $this->assertGreaterThanOrEqual(1, $previewRes->json('match_count'));

        // 3. Match incoming article against active monitors
        $alertCount = $monitorService->matchAndAlert($this->sampleArticle, 'article');
        $this->assertGreaterThanOrEqual(1, $alertCount);

        // 4. Verify in-app notification delivered
        $this->assertEquals(1, Notification::where('user_id', $this->subscriberA->id)->where('category', 'monitor_alert')->count());

        // 5. Verify unconfigured email channel is logged honestly without fake success
        $deliveryLog = NotificationDeliveryLog::where('channel', 'email')->latest()->first();
        $this->assertNotNull($deliveryLog);
        $this->assertEquals('unconfigured_channel', $deliveryLog->status);
    }

    public function test_notification_lifecycle_read_archive_and_unconfigured_channel_safety(): void
    {
        $notifService = app(SubscriberNotificationService::class);

        // Send sample notification
        $notif = $notifService->sendNotification($this->subscriberA, [
            'category' => 'breaking',
            'title' => 'Breaking Silicon Sovereignty Announcement',
            'message' => 'European Commission clears state aid for joint advanced packaging center.',
            'delivery_channel' => 'telegram', // unconfigured channel
        ]);

        $this->assertFalse($notif->is_read);

        // 1. Mark as Read
        $readRes = $this->actingAs($this->subscriberA, 'sanctum')->postJson("/api/v1/workspace/notifications/{$notif->id}/read");
        $readRes->assertOk();
        $this->assertTrue($notif->fresh()->is_read);

        // 2. Mark All Read
        $markAllRes = $this->actingAs($this->subscriberA, 'sanctum')->postJson('/api/v1/workspace/notifications/mark-all-read');
        $markAllRes->assertOk();

        // 3. Archive (Soft delete)
        $archiveRes = $this->actingAs($this->subscriberA, 'sanctum')->postJson("/api/v1/workspace/notifications/{$notif->id}/archive");
        $archiveRes->assertOk();
        $this->assertTrue($notif->fresh()->trashed());

        // 4. Hard Delete
        $deleteRes = $this->actingAs($this->subscriberA, 'sanctum')->deleteJson("/api/v1/workspace/notifications/{$notif->id}");
        $deleteRes->assertOk();
        $this->assertDatabaseMissing('notifications', ['id' => $notif->id]);
    }

    public function test_personal_library_and_collections_management(): void
    {
        // 1. Create Collection
        $colRes = $this->actingAs($this->subscriberA, 'sanctum')->postJson('/api/v1/workspace/collections', [
            'name' => 'Foundry Geopolitics Dossier',
            'description' => 'Cross-border state aid and capacity allocations.',
            'color' => '#d4af37',
        ]);
        $colRes->assertCreated();
        $collectionId = $colRes->json('collection.id');

        // 2. Save Article with Private Notes
        $saveRes = $this->actingAs($this->subscriberA, 'sanctum')->postJson('/api/v1/workspace/library', [
            'item_type' => 'article',
            'item_id' => $this->sampleArticle->id,
            'collection_id' => $collectionId,
            'tags' => ['lithography', 'TSMC', 'Dresden'],
            'private_notes' => 'Strictly confidential note: verify cleanroom utility readiness before Q3 board briefing.',
        ]);
        $saveRes->assertCreated();
        $libraryItemId = $saveRes->json('item.id');

        // 3. List Library Items
        $listRes = $this->actingAs($this->subscriberA, 'sanctum')->getJson('/api/v1/workspace/library');
        $listRes->assertOk();
        $this->assertEquals(1, $listRes->json('items.total'));
        $this->assertEquals('TSMC and ASML Finalize European Fab Tooling Integration', $listRes->json('items.data.0.title'));

        // 4. Export Collection in Markdown
        $exportRes = $this->actingAs($this->subscriberA, 'sanctum')->getJson("/api/v1/workspace/collections/{$collectionId}/export?format=markdown");
        $exportRes->assertOk();
        $this->assertStringContainsString('Foundry Geopolitics Dossier', $exportRes->json('export'));
        $this->assertStringContainsString('Strictly confidential note', $exportRes->json('export'));
    }

    public function test_private_notes_strict_privacy(): void
    {
        $libService = app(PersonalLibraryService::class);

        // Subscriber A writes a private note
        $savedItem = $libService->saveItem($this->subscriberA, [
            'item_type' => 'article',
            'item_id' => $this->sampleArticle->id,
            'private_notes' => 'Internal proprietary investment calculation: +12% target margin.',
        ]);

        // 1. Subscriber B cannot see Subscriber A's item in their library
        $subBList = $this->actingAs($this->subscriberB, 'sanctum')->getJson('/api/v1/workspace/library');
        $subBList->assertOk();
        $this->assertEquals(0, $subBList->json('items.total'));

        // 2. Subscriber B cannot update or delete Subscriber A's saved record
        $unauthorizedEdit = $this->actingAs($this->subscriberB, 'sanctum')->putJson("/api/v1/workspace/library/{$savedItem->id}", [
            'private_notes' => 'Tampered notes',
        ]);
        $unauthorizedEdit->assertNotFound();

        // 3. Admin cannot view individual private notes via subscriber admin overview
        $adminOverview = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/subscriber-intelligence/overview');
        $adminOverview->assertOk();
        $this->assertStringNotContainsString('Internal proprietary investment calculation', $adminOverview->getContent());
    }

    public function test_reading_history_and_signal_reset(): void
    {
        // 1. Record interaction signal
        $interactRes = $this->actingAs($this->subscriberA, 'sanctum')->postJson('/api/v1/workspace/interact', [
            'action' => 'view',
            'target_type' => 'article',
            'target_id' => $this->sampleArticle->id,
        ]);
        $interactRes->assertOk();

        // 2. Check Reading History
        $historyRes = $this->actingAs($this->subscriberA, 'sanctum')->getJson('/api/v1/workspace/reading-history');
        $historyRes->assertOk();
        $this->assertEquals(1, $historyRes->json('history.total'));

        // 3. Clear History
        $clearRes = $this->actingAs($this->subscriberA, 'sanctum')->deleteJson('/api/v1/workspace/reading-history');
        $clearRes->assertOk();
        $this->assertEquals(0, $this->actingAs($this->subscriberA, 'sanctum')->getJson('/api/v1/workspace/reading-history')->json('history.total'));

        // 4. Reset Signals
        $resetRes = $this->actingAs($this->subscriberA, 'sanctum')->postJson('/api/v1/workspace/reset-signals');
        $resetRes->assertOk();
    }

    public function test_grounded_personalized_research_with_citations(): void
    {
        $wlService = app(WatchlistService::class);
        $watchlist = $wlService->createWatchlist($this->subscriberA, [
            'name' => 'Lithography Leaders',
        ]);
        $wlService->addItem($this->subscriberA, $watchlist->id, [
            'entity_name' => 'ASML',
            'entity_type' => 'company',
        ]);

        $researchRes = $this->actingAs($this->subscriberA, 'sanctum')->postJson('/api/v1/workspace/research/query', [
            'query' => 'What are ASML High-NA lithography deployment timelines?',
            'watchlist_id' => $watchlist->id,
        ]);

        $researchRes->assertOk();
        $researchRes->assertJsonStructure([
            'success',
            'query',
            'answer',
            'grounded_citations',
            'evidence_limitations',
            'generated_at',
        ]);

        $this->assertNotEmpty($researchRes->json('answer'));
        $this->assertIsArray($researchRes->json('grounded_citations'));
    }

    public function test_plan_entitlement_limits_enforcement(): void
    {
        $wlService = app(WatchlistService::class);

        // Standard subscriber limit is 5 watchlists
        for ($i = 1; $i <= 5; $i++) {
            $wlService->createWatchlist($this->subscriberA, [
                'name' => "Watchlist #{$i}",
            ]);
        }

        // 6th watchlist creation must be rejected
        $rejectRes = $this->actingAs($this->subscriberA, 'sanctum')->postJson('/api/v1/workspace/watchlists', [
            'name' => 'Watchlist #6 (Over limit)',
        ]);
        $rejectRes->assertStatus(422);
        $rejectRes->assertJsonValidationErrors(['name']);
    }

    public function test_admin_aggregate_telemetry_and_policy_update(): void
    {
        // 1. Overview Telemetry
        $overviewRes = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/subscriber-intelligence/overview');
        $overviewRes->assertOk();
        $overviewRes->assertJsonStructure([
            'success',
            'metrics' => [
                'total_watchlists',
                'total_monitors',
                'active_monitors',
                'total_saved_library_items',
                'total_notifications_sent',
                'delivery_health' => [
                    'delivered',
                    'sent',
                    'failed',
                    'unconfigured_channel',
                ],
            ],
        ]);

        // 2. Notification Delivery Logs
        $logsRes = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/admin/subscriber-intelligence/delivery-logs');
        $logsRes->assertOk();
        $this->assertArrayHasKey('logs', $logsRes->json());

        // 3. Update Policy Limits for Tier
        $policyRes = $this->actingAs($this->adminUser, 'sanctum')->postJson('/api/v1/admin/subscriber-intelligence/policies', [
            'tier' => 'premium',
            'max_watchlists' => 30,
            'max_entities_per_watchlist' => 50,
            'max_active_monitors' => 20,
            'daily_research_queries' => 80,
        ]);
        $policyRes->assertOk();
    }
}
