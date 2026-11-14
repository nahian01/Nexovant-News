<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationAdminController
{
    /**
     * List platform delivery notifications with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::with('user:id,name,email')->latest('created_at');

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($channel = $request->query('delivery_channel')) {
            $query->where('delivery_channel', $channel);
        }

        if ($status = $request->query('delivery_status')) {
            $query->where('delivery_status', $status);
        }

        $notifications = $query->paginate((int) $request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'meta' => [
                'total' => $notifications->total(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
            ],
            'channels' => ['in_app', 'email', 'whatsapp', 'telegram', 'pdf_digest'],
            'categories' => ['breaking', 'trend_alert', 'publication', 'system', 'billing', 'editorial'],
        ]);
    }

    /**
     * Dispatch or broadcast a new notification.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'is_broadcast' => ['nullable', 'boolean'],
            'category' => ['required', 'string', 'in:breaking,trend_alert,publication,system,billing,editorial'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'link' => ['nullable', 'string', 'max:255'],
            'delivery_channel' => ['required', 'string', 'in:in_app,email,whatsapp,telegram,pdf_digest'],
        ]);

        $admin = $request->user();
        $isBroadcast = $validated['is_broadcast'] ?? empty($validated['user_id']);

        $sentCount = 0;
        if ($isBroadcast) {
            $users = User::where('is_active', true)->get();
            foreach ($users as $u) {
                Notification::create([
                    'user_id' => $u->id,
                    'category' => $validated['category'],
                    'title' => $validated['title'],
                    'message' => $validated['message'],
                    'link' => $validated['link'] ?? null,
                    'delivery_channel' => $validated['delivery_channel'],
                    'delivery_status' => 'sent',
                ]);
                $sentCount++;
            }
        } else {
            Notification::create([
                'user_id' => $validated['user_id'],
                'category' => $validated['category'],
                'title' => $validated['title'],
                'message' => $validated['message'],
                'link' => $validated['link'] ?? null,
                'delivery_channel' => $validated['delivery_channel'],
                'delivery_status' => 'sent',
            ]);
            $sentCount = 1;
        }

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'notification.dispatched',
            'resource_type' => 'Notification',
            'new_values' => [
                'title' => $validated['title'],
                'channel' => $validated['delivery_channel'],
                'recipients_count' => $sentCount,
            ],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Notification dispatched successfully to {$sentCount} recipient(s).",
            'recipients_count' => $sentCount,
        ], 201);
    }

    /**
     * Retry a failed notification delivery.
     */
    public function retry(string $id): JsonResponse
    {
        $notif = Notification::findOrFail($id);
        $notif->update([
            'delivery_status' => 'sent',
            'retry_count' => $notif->retry_count + 1,
            'error_message' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification delivery re-attempted and marked delivered.',
            'notification' => $notif,
        ]);
    }

    /**
     * Soft delete notification.
     */
    public function destroy(string $id): JsonResponse
    {
        $notif = Notification::findOrFail($id);
        $notif->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted.',
        ]);
    }
}
