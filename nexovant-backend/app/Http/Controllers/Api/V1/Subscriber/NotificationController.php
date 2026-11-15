<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscriber;

use App\Services\Workspace\SubscriberNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController
{
    public function __construct(
        protected SubscriberNotificationService $notificationService
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
        $count = $this->notificationService->markAllAsRead($request->user());

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
                'digest_frequency' => ['nullable', 'string', 'in:real_time,daily,weekly,disabled'],
                'quiet_hours_start' => ['nullable', 'string'],
                'quiet_hours_end' => ['nullable', 'string'],
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
}
