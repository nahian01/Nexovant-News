<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\NotificationDeliveryLog;
use App\Models\NotificationRule;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\SubscriberNotificationPreference;
use App\Models\TelegramLinkToken;
use App\Models\User;
use App\Models\WebPushSubscription;
use App\Services\Notification\DTO\NotificationEventDTO;
use App\Services\Notification\NotificationEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class CentralizedNotificationEngineStage11Test extends TestCase
{
    use RefreshDatabase;

    protected User $subscriber;
    protected User $otherSubscriber;
    protected User $admin;
    protected Organization $organization;
    protected NotificationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(NotificationEngine::class);

        $this->subscriber = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Alexander Vance',
            'email' => 'subscriber@nexovant.test',
            'password' => bcrypt('Password123!'),
            'account_type' => 'subscriber',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->otherSubscriber = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Elena Rostova',
            'email' => 'other@nexovant.test',
            'password' => bcrypt('Password123!'),
            'account_type' => 'subscriber',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Platform Administrator',
            'email' => 'admin@nexovant.si',
            'password' => bcrypt('Password123!'),
            'account_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->organization = Organization::create([
            'id' => (string) Str::uuid(),
            'owner_id' => $this->subscriber->id,
            'name' => 'Vance Capital Partners',
            'slug' => 'vance-capital',
            'is_active' => true,
        ]);

        // Add subscriber to organization
        OrganizationMember::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->organization->id,
            'user_id' => $this->subscriber->id,
            'role' => 'owner',
        ]);
    }

    /**
     * Test 1: NotificationEventDTO instantiation and serialization.
     */
    public function test_notification_event_dto_instantiation(): void
    {
        $dto = NotificationEventDTO::fromArray([
            'event_type' => 'intelligence.breaking_news',
            'source_module' => 'intelligence',
            'recipient_ids' => [$this->subscriber->id],
            'title' => 'NVIDIA Acquires Optical Switching Lab',
            'message' => 'NVIDIA Corporation has finalized the acquisition of Photonic Interconnects for $1.2B.',
            'link' => '/articles/nvidia-photonic-acquisition',
            'priority' => 'urgent',
            'organization_id' => $this->organization->id,
            'metadata' => ['ticker' => 'NVDA', 'deal_size' => '$1.2B'],
        ]);

        $this->assertSame('intelligence.breaking_news', $dto->eventType);
        $this->assertSame('intelligence', $dto->sourceModule);
        $this->assertSame('urgent', $dto->priority);
        $this->assertSame($this->organization->id, $dto->tenantId);
        $this->assertCount(1, $dto->recipientIds);
        $this->assertSame('NVDA', $dto->metadata['ticker']);

        $array = $dto->toArray();
        $this->assertSame('intelligence.breaking_news', $array['event_type']);
        $this->assertSame($this->organization->id, $array['organization_id']);
    }

    /**
     * Test 2: NotificationEngine dispatches in-app and records delivery log.
     */
    public function test_central_engine_dispatches_in_app_and_writes_delivery_log(): void
    {
        $dto = new NotificationEventDTO(
            eventType: 'watchlist.entity_alert',
            sourceModule: 'workspace',
            recipientIds: [$this->subscriber->id],
            title: 'TSMC 2nm Fab Yield Disclosure',
            message: 'TSMC disclosed volume trial wafer yield exceeding 78% for N2 gate-all-around nodes.',
            link: '/workspace/watchlists',
            priority: 'high',
            metadata: ['company' => 'TSMC'],
            category: 'trend_alert',
        );

        $result = $this->engine->dispatch($dto);

        $this->assertSame('dispatched', $result['status']);
        $this->assertSame(1, $result['dispatched_count']);
        $this->assertCount(1, $result['notifications']);

        $notification = $result['notifications'][0];
        $this->assertSame($this->subscriber->id, $notification->user_id);
        $this->assertSame('TSMC 2nm Fab Yield Disclosure', $notification->title);
        $this->assertSame('high', $notification->priority);
        $this->assertSame('trend_alert', $notification->category);

        // Verify delivery log exists
        $this->assertDatabaseHas('notification_delivery_logs', [
            'notification_id' => $notification->id,
            'channel' => 'in_app',
            'status' => 'delivered',
        ]);
    }

    /**
     * Test 3: Idempotency gate suppresses duplicate notifications within window.
     */
    public function test_idempotency_gate_prevents_duplicate_notifications(): void
    {
        $dto = new NotificationEventDTO(
            eventType: 'billing.payment_confirmed',
            sourceModule: 'billing',
            recipientIds: [$this->subscriber->id],
            title: 'Payment Receipt: Invoice #INV-2026-9901',
            message: 'Your payment of $199.00 has been confirmed successfully.',
            idempotencyKey: 'billing:inv-2026-9901',
            priority: 'high',
            category: 'billing',
        );

        // First dispatch: Success
        $firstResult = $this->engine->dispatch($dto);
        $this->assertSame('dispatched', $firstResult['status']);
        $this->assertSame(1, $firstResult['dispatched_count']);

        // Second dispatch with same idempotency key: Suppressed
        $secondResult = $this->engine->dispatch($dto);
        $this->assertSame('duplicate_suppressed', $secondResult['status']);
        $this->assertSame(0, $secondResult['dispatched_count']);
        $this->assertEmpty($secondResult['notifications']);
    }

    /**
     * Test 4: Notification rules engine applies priority and channel configurations.
     */
    public function test_notification_rule_evaluation_applies_priority_and_channels(): void
    {
        // Create custom notification rule
        NotificationRule::create([
            'id' => (string) Str::uuid(),
            'name' => 'Custom Executive Rule',
            'event_type' => 'custom.regulatory_alert',
            'source_module' => 'editorial',
            'priority' => 'urgent',
            'channels' => ['in_app', 'email'],
            'is_active' => true,
        ]);

        $dto = new NotificationEventDTO(
            eventType: 'custom.regulatory_alert',
            sourceModule: 'editorial',
            recipientIds: [$this->subscriber->id],
            title: 'EU Antimonopoly Decision Published',
            message: 'European Commission released the full public text of the fine assessment.',
            priority: 'medium', // Should be elevated to 'urgent' by rule
        );

        $result = $this->engine->dispatch($dto);

        $this->assertSame('dispatched', $result['status']);
        $notification = $result['notifications'][0];
        $this->assertSame('urgent', $notification->priority);
    }

    /**
     * Test 5: Strict tenant isolation strips unauthorized recipients outside organization.
     */
    public function test_strict_tenant_isolation_filters_unauthorized_recipients(): void
    {
        // Target both subscriber (belongs to org) and otherSubscriber (does NOT belong to org)
        $dto = new NotificationEventDTO(
            eventType: 'organization.research_shared',
            sourceModule: 'organization',
            recipientIds: [$this->subscriber->id, $this->otherSubscriber->id],
            title: 'Confidential M&A Valuation Model Shared',
            message: 'Private organization research dossier shared with team members.',
            tenantId: $this->organization->id, // Tenant boundary
            priority: 'high',
        );

        $result = $this->engine->dispatch($dto);

        // Only subscriber should receive it; otherSubscriber should be stripped
        $this->assertSame(1, $result['dispatched_count']);
        $this->assertCount(1, $result['notifications']);
        $this->assertSame($this->subscriber->id, $result['notifications'][0]->user_id);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->otherSubscriber->id,
            'title' => 'Confidential M&A Valuation Model Shared',
        ]);
    }

    /**
     * Test 6: Quiet hours suppresses non-urgent alerts but allows urgent alerts.
     */
    public function test_quiet_hours_suppresses_non_urgent_alerts(): void
    {
        // Configure subscriber with quiet hours spanning all day (00:00 to 23:59)
        SubscriberNotificationPreference::updateOrCreate(
            ['user_id' => $this->subscriber->id],
            [
                'quiet_hours_start' => '00:00',
                'quiet_hours_end' => '23:59',
                'timezone' => 'UTC',
            ]
        );

        // 1. Non-urgent alert: Should be suppressed for digest
        $normalDto = new NotificationEventDTO(
            eventType: 'workspace.weekly_metrics',
            sourceModule: 'workspace',
            recipientIds: [$this->subscriber->id],
            title: 'Weekly Monitored Entities Digest',
            message: 'Your watchlist had 14 new updates this week.',
            priority: 'medium',
            idempotencyKey: 'quiet_hours_normal_test',
        );

        $normalResult = $this->engine->dispatch($normalDto);
        $this->assertSame('suppressed_for_digest', $normalResult['results'][$this->subscriber->id]['quiet_hours']);

        $this->assertDatabaseHas('notification_delivery_logs', [
            'notification_id' => $normalResult['notifications'][0]->id,
            'status' => 'suppressed_quiet_hours',
        ]);

        // 2. Urgent alert: Must bypass quiet hours immediately
        $urgentDto = new NotificationEventDTO(
            eventType: 'security.account_breach',
            sourceModule: 'system',
            recipientIds: [$this->subscriber->id],
            title: 'Critical Security Alert: New Login from Unknown IP',
            message: 'A new session was initiated from an unrecognized geographic region.',
            priority: 'urgent',
            idempotencyKey: 'quiet_hours_urgent_test',
        );

        $urgentResult = $this->engine->dispatch($urgentDto);
        $this->assertArrayNotHasKey('quiet_hours', $urgentResult['results'][$this->subscriber->id]);
    }

    /**
     * Test 7: User category preference filtering mutes non-transactional alerts.
     */
    public function test_user_category_preference_filtering(): void
    {
        // Mute 'trend_alert' in subscriber preferences
        SubscriberNotificationPreference::updateOrCreate(
            ['user_id' => $this->subscriber->id],
            [
                'category_settings' => [
                    'trend_alert' => false,
                    'breaking' => true,
                    'billing' => true,
                ],
            ]
        );

        // Attempt dispatch of muted category
        $dto = new NotificationEventDTO(
            eventType: 'trend.social_shift',
            sourceModule: 'workspace',
            recipientIds: [$this->subscriber->id],
            title: 'Minor Social Sentiment Oscillation',
            message: 'Trend velocity changed by 4%.',
            category: 'trend_alert',
            priority: 'low',
            idempotencyKey: 'muted_cat_test',
        );

        $result = $this->engine->dispatch($dto);
        $this->assertSame(0, $result['dispatched_count']);
        $this->assertSame(1, $result['suppressed_count']);
        $this->assertEmpty($result['notifications']);
    }

    /**
     * Test 8: Web push subscription registration and device management.
     */
    public function test_web_push_subscription_registration_and_dispatch(): void
    {
        // 1. Get VAPID key
        $vapidRes = $this->actingAs($this->subscriber, 'sanctum')
            ->getJson('/api/v1/notifications/push/vapid-key');

        $vapidRes->assertStatus(200);
        $vapidRes->assertJsonStructure(['success', 'public_key']);
        $this->assertNotEmpty($vapidRes->json('public_key'));

        // 2. Register push subscription
        $subRes = $this->actingAs($this->subscriber, 'sanctum')
            ->postJson('/api/v1/notifications/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-sub-device-1',
                'public_key' => 'sample-p256dh-key',
                'auth_token' => 'sample-auth-token',
                'user_agent' => 'Mozilla/5.0 Test Suite Agent',
            ]);

        $subRes->assertStatus(201);
        $this->assertDatabaseHas('web_push_subscriptions', [
            'user_id' => $this->subscriber->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-sub-device-1',
            'is_active' => true,
        ]);

        // 3. Unsubscribe
        $unsubRes = $this->actingAs($this->subscriber, 'sanctum')
            ->postJson('/api/v1/notifications/push/unsubscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-sub-device-1',
            ]);

        $unsubRes->assertStatus(200);
        $this->assertDatabaseHas('web_push_subscriptions', [
            'user_id' => $this->subscriber->id,
            'is_active' => false,
        ]);
    }

    /**
     * Test 9: Telegram linking token lifecycle, verification, and test dispatch.
     */
    public function test_telegram_link_token_lifecycle_and_verification(): void
    {
        // 1. Generate link token
        $tokenRes = $this->actingAs($this->subscriber, 'sanctum')
            ->postJson('/api/v1/notifications/telegram/link-token');

        $tokenRes->assertStatus(200);
        $tokenRes->assertJsonStructure(['success', 'token', 'bot_username', 'deep_link', 'expires_at']);
        $token = $tokenRes->json('token');

        $this->assertDatabaseHas('telegram_link_tokens', [
            'user_id' => $this->subscriber->id,
            'token' => $token,
        ]);

        // 2. Verify token with chat ID
        $verifyRes = $this->actingAs($this->subscriber, 'sanctum')
            ->postJson('/api/v1/notifications/telegram/verify', [
                'token' => $token,
                'chat_id' => '987654321',
            ]);

        $verifyRes->assertStatus(200);
        $this->assertDatabaseHas('subscriber_notification_preferences', [
            'user_id' => $this->subscriber->id,
            'telegram_chat_id' => '987654321',
        ]);

        // 3. Disconnect Telegram
        $disconnectRes = $this->actingAs($this->subscriber, 'sanctum')
            ->postJson('/api/v1/notifications/telegram/disconnect');

        $disconnectRes->assertStatus(200);
        $this->assertDatabaseHas('subscriber_notification_preferences', [
            'user_id' => $this->subscriber->id,
            'telegram_chat_id' => null,
        ]);
    }

    /**
     * Test 10: WhatsApp channel adapter reports truthful onboarding status.
     */
    public function test_whatsapp_channel_truthful_onboarding_evaluation(): void
    {
        $res = $this->actingAs($this->subscriber, 'sanctum')
            ->getJson('/api/v1/notifications/whatsapp/status');

        $res->assertStatus(200);
        $res->assertJsonStructure([
            'success',
            'data' => [
                'provider',
                'is_configured',
                'merchant_onboarding_status',
                'required_assets',
                'compliance_note',
            ],
        ]);

        $this->assertSame('Meta WhatsApp Business Cloud API (v21.0)', $res->json('data.provider'));
    }

    /**
     * Test 11: Scheduled notification digest command executes cleanly.
     */
    public function test_scheduled_notification_digest_command(): void
    {
        // Create an unread notification for subscriber
        Notification::create([
            'user_id' => $this->subscriber->id,
            'category' => 'publication',
            'title' => 'Quantum Cryptography Semiconductor Benchmark',
            'message' => 'New benchmark disclosures evaluated by Nexovant Research.',
            'delivery_channel' => 'in_app',
            'delivery_status' => 'sent',
            'priority' => 'medium',
            'is_read' => false,
            'created_at' => Carbon::now()->subHours(2),
        ]);

        // Configure subscriber for daily digest
        SubscriberNotificationPreference::updateOrCreate(
            ['user_id' => $this->subscriber->id],
            ['digest_frequency' => 'daily']
        );

        $exitCode = Artisan::call('notifications:send-digests', ['--frequency' => 'daily']);
        $this->assertSame(0, $exitCode);

        // Verify digest notification was created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->subscriber->id,
            'category' => 'publication',
            'priority' => 'low',
        ]);
    }

    /**
     * Test 12: Subscriber REST API: index, mark read, mark all read, and delete.
     */
    public function test_subscriber_api_endpoints_index_mark_read_mark_all_and_delete(): void
    {
        $notif1 = Notification::create([
            'user_id' => $this->subscriber->id,
            'category' => 'breaking',
            'title' => 'API Test Notification 1',
            'message' => 'Message 1 body',
            'delivery_channel' => 'in_app',
            'delivery_status' => 'delivered',
            'priority' => 'urgent',
            'is_read' => false,
        ]);

        $notif2 = Notification::create([
            'user_id' => $this->subscriber->id,
            'category' => 'system',
            'title' => 'API Test Notification 2',
            'message' => 'Message 2 body',
            'delivery_channel' => 'in_app',
            'delivery_status' => 'delivered',
            'priority' => 'low',
            'is_read' => false,
        ]);

        // 1. Index
        $indexRes = $this->actingAs($this->subscriber, 'sanctum')
            ->getJson('/api/v1/workspace/notifications');

        $indexRes->assertStatus(200);
        $this->assertSame(2, $indexRes->json('unread_count'));

        // 2. Mark single read
        $markRes = $this->actingAs($this->subscriber, 'sanctum')
            ->postJson("/api/v1/workspace/notifications/{$notif1->id}/read");

        $markRes->assertStatus(200);
        $this->assertTrue(Notification::find($notif1->id)->is_read);

        // 3. Mark all read
        $allRes = $this->actingAs($this->subscriber, 'sanctum')
            ->postJson('/api/v1/workspace/notifications/mark-all-read');

        $allRes->assertStatus(200);
        $this->assertTrue(Notification::find($notif2->id)->is_read);

        // 4. Delete
        $delRes = $this->actingAs($this->subscriber, 'sanctum')
            ->deleteJson("/api/v1/workspace/notifications/{$notif1->id}");

        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('notifications', ['id' => $notif1->id]);
    }

    /**
     * Test 13: Administrator notification control center endpoints (metrics, logs, broadcast, rules).
     */
    public function test_admin_notification_endpoints_metrics_logs_broadcast_and_rules(): void
    {
        // 1. Metrics endpoint
        $metricsRes = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/notifications/metrics');

        $metricsRes->assertStatus(200);
        $metricsRes->assertJsonStructure([
            'success',
            'metrics' => [
                'total_24h',
                'unread_total',
                'delivered_24h',
                'failed_24h',
                'delivery_rate',
                'channel_breakdown',
                'active_rules_count',
            ],
        ]);

        // 2. Broadcast with Preview
        $previewRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/notifications/broadcast', [
                'target_audience' => 'all',
                'category' => 'system',
                'priority' => 'high',
                'title' => 'Scheduled Maintenance Window Notice',
                'message' => 'Platform maintenance scheduled for Saturday 02:00 UTC.',
                'preview' => true,
            ]);

        $previewRes->assertStatus(200);
        $this->assertTrue($previewRes->json('preview'));
        $this->assertGreaterThanOrEqual(1, $previewRes->json('recipient_count'));

        // 3. Broadcast Real Dispatch
        $dispatchRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/notifications/broadcast', [
                'target_audience' => 'all',
                'category' => 'system',
                'priority' => 'high',
                'title' => 'Scheduled Maintenance Window Notice',
                'message' => 'Platform maintenance scheduled for Saturday 02:00 UTC.',
                'preview' => false,
            ]);

        $dispatchRes->assertStatus(201);
        $this->assertGreaterThanOrEqual(1, $dispatchRes->json('dispatched_count'));

        // 4. Inspect Delivery Logs
        $logsRes = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/notifications/logs');

        $logsRes->assertStatus(200);
        $this->assertNotEmpty($logsRes->json('data'));

        // 5. Rules list and update
        $rulesRes = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/notifications/rules');

        $rulesRes->assertStatus(200);
        $ruleList = $rulesRes->json('data');
        if (!empty($ruleList)) {
            $firstRule = $ruleList[0];
            $updateRuleRes = $this->actingAs($this->admin, 'sanctum')
                ->putJson("/api/v1/admin/notifications/rules/{$firstRule['id']}", [
                    'priority' => 'urgent',
                    'is_active' => true,
                ]);

            $updateRuleRes->assertStatus(200);
            $this->assertSame('urgent', NotificationRule::find($firstRule['id'])->priority);
        }
    }
}
