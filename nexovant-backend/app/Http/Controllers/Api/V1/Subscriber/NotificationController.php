<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscriber;

use App\Models\Notification;
use App\Models\TelegramLinkToken;
use App\Models\WebPushSubscription;
use App\Services\Notification\Adapters\TelegramChannelAdapter;
use App\Services\Notification\Adapters\WebPushChannelAdapter;
use App\Services\Notification\Adapters\WhatsAppChannelAdapter;
use App\Services\Workspace\SubscriberNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class NotificationController
{
    public function __construct(
        protected SubscriberNotificationService $notificationService,
        protected WebPushChannelAdapter $webPushAdapter,
        protected TelegramChannelAdapter $telegramAdapter,
        protected WhatsAppChannelAdapter $whatsappAdapter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $notifications = $this->notificationService->listNotifications(
            $request->user(),
            $request->all(),
            (int) $request->query('per_page', 25)
        );

        $unreadCount = $this->notificationService->getUnreadCount($request->user());

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'categories' => ['all', 'breaking', 'trend_alert', 'publication', 'system', 'billing', 'editorial'],
            'priorities' => ['all', 'urgent', 'high', 'medium', 'low'],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $this->notificationService->markAsRead($request->user(), $id);

        return response()->json([
            'success' => true,
            'notification' => $notification,
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $category = $request->input('category');
        $count = $this->notificationService->markAllAsRead($request->user(), $category);

        return response()->json([
            'success' => true,
            'message' => "Marked {$count} notifications as read.",
            'updated_count' => $count,
        ]);
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        $this->notificationService->archiveNotification($request->user(), $id);

        return response()->json([
            'success' => true,
            'message' => 'Notification archived.',
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->notificationService->deleteNotification($request->user(), $id);

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted.',
        ]);
    }

    public function preferences(Request $request): JsonResponse
    {
        if ($request->isMethod('POST') || $request->isMethod('PUT')) {
            $validated = $request->validate([
                'channels' => ['nullable', 'array'],
                'category_settings' => ['nullable', 'array'],
                'digest_frequency' => ['nullable', 'string', 'in:realtime,hourly,daily,weekly,disabled'],
                'quiet_hours_start' => ['nullable', 'string'],
                'quiet_hours_end' => ['nullable', 'string'],
                'telegram_chat_id' => ['nullable', 'string'],
                'whatsapp_phone' => ['nullable', 'string'],
                'timezone' => ['nullable', 'string'],
            ]);

            $pref = $this->notificationService->updatePreferences($request->user(), $validated);

            return response()->json([
                'success' => true,
                'message' => 'Notification settings updated.',
                'preferences' => $pref,
            ]);
        }

        $pref = $this->notificationService->getPreferences($request->user());

        return response()->json([
            'success' => true,
            'preferences' => $pref,
        ]);
    }

    // ---------------------------------------------------------------------
    // Web Push Notification Endpoints
    // ---------------------------------------------------------------------

    public function getVapidKey(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'public_key' => $this->webPushAdapter->getVapidPublicKey(),
        ]);
    }

    public function subscribePush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
            'public_key' => ['nullable', 'string'],
            'keys.p256dh' => ['nullable', 'string'],
            'auth_token' => ['nullable', 'string'],
            'keys.auth' => ['nullable', 'string'],
            'user_agent' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $endpoint = $validated['endpoint'];
        $publicKey = $validated['public_key'] ?? $validated['keys']['p256dh'] ?? '';
        $authToken = $validated['auth_token'] ?? $validated['keys']['auth'] ?? '';
        $userAgent = $validated['user_agent'] ?? $request->header('User-Agent');

        $subscription = WebPushSubscription::updateOrCreate(
            ['user_id' => $user->id, 'endpoint' => $endpoint],
            [
                'public_key' => $publicKey,
                'auth_token' => $authToken,
                'user_agent' => $userAgent,
                'is_active' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Web push subscription registered successfully.',
            'subscription_id' => $subscription->id,
        ], 201);
    }

    public function unsubscribePush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        WebPushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint', $validated['endpoint'])
            ->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Web push subscription revoked.',
        ]);
    }

    // ---------------------------------------------------------------------
    // Telegram Bot Integration Endpoints
    // ---------------------------------------------------------------------

    public function generateTelegramToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = Str::random(32);
        $expiresAt = Carbon::now()->addMinutes(15);

        TelegramLinkToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        $botUsername = (string) Config::get('services.telegram.bot_username', env('TELEGRAM_BOT_USERNAME', 'NexovantIntelBot'));
        $deepLink = "https://t.me/{$botUsername}?start={$token}";

        return response()->json([
            'success' => true,
            'token' => $token,
            'bot_username' => $botUsername,
            'deep_link' => $deepLink,
            'expires_at' => $expiresAt->toIso8601String(),
            'instructions' => "Open the link or message @{$botUsername} on Telegram and send: /start {$token}",
        ]);
    }

    public function verifyTelegram(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'chat_id' => ['required', 'string'],
        ]);

        $linkToken = TelegramLinkToken::where('token', $validated['token'])
            ->valid()
            ->first();

        if (!$linkToken) {
            return response()->json([
                'success' => false,
                'message' => 'Linking token is invalid, expired, or already used.',
            ], 422);
        }

        $user = $linkToken->user;
        $this->notificationService->updatePreferences($user, [
            'telegram_chat_id' => $validated['chat_id'],
        ]);

        $linkToken->update(['used_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Telegram account linked successfully.',
            'chat_id' => $validated['chat_id'],
        ]);
    }

    public function disconnectTelegram(Request $request): JsonResponse
    {
        $this->notificationService->updatePreferences($request->user(), [
            'telegram_chat_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Telegram account disconnected.',
        ]);
    }

    public function testTelegram(Request $request): JsonResponse
    {
        $user = $request->user();
        $pref = $this->notificationService->getPreferences($user);

        if (empty($pref->telegram_chat_id)) {
            return response()->json([
                'success' => false,
                'message' => 'No Telegram account linked yet. Generate a linking token first.',
            ], 400);
        }

        $dummyNotification = new Notification([
            'id' => (string) Str::uuid(),
            'title' => 'Nexovant Intelligence Test Alert',
            'message' => 'Your Telegram integration is verified and operational. You will receive high-priority technology intelligence alerts directly in this chat.',
            'category' => 'system',
            'priority' => 'high',
            'link' => '/workspace/notifications',
        ]);

        $result = $this->telegramAdapter->send($dummyNotification, $user, [
            'chat_id' => $pref->telegram_chat_id,
        ]);

        return response()->json([
            'success' => $result['status'] === 'delivered',
            'status' => $result['status'],
            'details' => $result,
        ]);
    }

    // ---------------------------------------------------------------------
    // WhatsApp Status Endpoint
    // ---------------------------------------------------------------------

    public function whatsappStatus(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->whatsappAdapter->getOnboardingStatus(),
        ]);
    }
}
