<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AIRoutingLog;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SystemAdminController
{
    /**
     * Get system configuration thresholds and settings from persistent backend storage.
     */
    public function settings(Request $request): JsonResponse
    {
        $settings = $this->loadSettings();

        return response()->json([
            'success' => true,
            'settings' => $settings,
        ]);
    }

    /**
     * Update system configuration and persist to backend storage with audit logging.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $admin = $request->user();
        $incoming = $request->all();

        $current = $this->loadSettings();
        $merged = array_merge($current, $incoming);

        $path = storage_path('app/settings.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Sync fetch interval to news sources if modified
        if (isset($incoming['feed_fetch_interval_minutes']) || isset($incoming['rss_polling_interval_minutes'])) {
            $interval = (int) ($incoming['feed_fetch_interval_minutes'] ?? $incoming['rss_polling_interval_minutes'] ?? 15);
            \App\Models\NewsSource::where('is_active', true)->update(['fetch_interval_minutes' => max(1, $interval)]);
        }

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'system.settings_updated',
            'resource_type' => 'SystemConfig',
            'new_values' => $incoming,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'System configuration parameters updated and applied to production worker pool.',
            'settings' => $merged,
        ]);
    }

    /**
     * Get real security audit logs with pagination and search.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $query = AuditLog::with('user:id,name,email')->latest('created_at');

        if ($action = $request->query('action')) {
            $query->where('action', 'like', "%{$action}%");
        }

        if ($resource = $request->query('resource_type')) {
            $query->where('resource_type', $resource);
        }

        $logs = $query->paginate((int) $request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'total' => $logs->total(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ]
        ]);
    }

    /**
     * Get real AI routing logs.
     */
    public function aiLogs(Request $request): JsonResponse
    {
        $query = AIRoutingLog::with(['provider:id,name,slug', 'model:id,display_name', 'credential:id,display_name', 'user:id,name'])
            ->latest('created_at');

        if ($task = $request->query('task_type')) {
            $query->where('task_type', $task);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $logs = $query->paginate((int) $request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'total' => $logs->total(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ]
        ]);
    }

    private function loadSettings(): array
    {
        $path = storage_path('app/settings.json');
        if (File::exists($path)) {
            $decoded = json_decode(File::get($path), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [
            'platform_name' => 'Nexovant Global Executive Intelligence Network',
            'primary_domain' => env('APP_URL', 'https://nexovant.si'),
            'story_consolidation_similarity_threshold' => 0.85,
            'verification_minimum_credibility' => 0.75,
            'breaking_news_importance_threshold' => 9.0,
            'consecutive_circuit_trip_failures' => 3,
            'circuit_cooldown_seconds' => 180,
            'rss_polling_interval_minutes' => 15,
            'social_ingestion_interval_minutes' => 30,
            'max_daily_pdf_concurrency' => 2,
            'public_registration_open' => true,
            'require_mfa_for_admins' => true,
            'default_ai_currency' => 'USD',
            'default_task_timeout_seconds' => 30,
        ];
    }
}
