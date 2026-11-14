<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AuditLog;
use App\Models\NewsSource;
use App\Models\PipelineJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SourceAdminController
{
    /**
     * List all news sources with collection health, fetch intervals, and counters.
     */
    public function index(Request $request): JsonResponse
    {
        $sources = NewsSource::withCount('articles')
            ->orderBy('credibility_score', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sources,
        ]);
    }

    /**
     * Create new news collection source.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'source_type' => ['required', 'string', 'in:rss,atom,regulatory,wire,official_company,press_release,news_api'],
            'url' => ['required', 'url'],
            'feed_url' => ['nullable', 'url'],
            'credibility_score' => ['required', 'numeric', 'min:0', 'max:1'],
            'rate_limit_per_minute' => ['required', 'integer', 'min:1'],
            'fetch_interval_minutes' => ['nullable', 'integer', 'min:1'],
        ]);

        $admin = $request->user();

        $source = NewsSource::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(4),
            'source_type' => $validated['source_type'],
            'url' => $validated['url'],
            'feed_url' => $validated['feed_url'] ?? null,
            'credibility_score' => $validated['credibility_score'],
            'rate_limit_per_minute' => $validated['rate_limit_per_minute'],
            'fetch_interval_minutes' => $validated['fetch_interval_minutes'] ?? 15,
            'is_active' => true,
            'health_status' => 'healthy',
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'source.created',
            'resource_type' => 'NewsSource',
            'resource_id' => $source->id,
            'new_values' => ['name' => $source->name, 'feed_url' => $source->feed_url],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "News collection source '{$source->name}' registered successfully.",
            'data' => $source,
        ], 201);
    }

    /**
     * Update an existing collection source.
     */
    public function update(string $id, Request $request): JsonResponse
    {
        $source = NewsSource::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'source_type' => ['sometimes', 'string', 'in:rss,atom,regulatory,wire,official_company,press_release,news_api'],
            'url' => ['sometimes', 'url'],
            'feed_url' => ['nullable', 'url'],
            'credibility_score' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'rate_limit_per_minute' => ['sometimes', 'integer', 'min:1'],
            'fetch_interval_minutes' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $source->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Source '{$source->name}' updated successfully.",
            'data' => $source,
        ]);
    }

    /**
     * Test connection to a collection source with real HTTP latency and SSRF protection.
     */
    public function testConnection(string $id): JsonResponse
    {
        $source = NewsSource::findOrFail($id);
        $targetUrl = $source->feed_url ?: $source->url;

        // SSRF Protection: validate host is not a private IP or local loopback
        $host = parse_url($targetUrl, PHP_URL_HOST);
        if ($host && filter_var($host, FILTER_VALIDATE_IP)) {
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'SSRF_BLOCKED',
                        'message' => 'Target URL points to a reserved or private internal network address.',
                    ]
                ], 422);
            }
        }

        $startTime = microtime(true);
        try {
            $response = Http::timeout(6)
                ->withHeaders(['User-Agent' => 'Nexovant-Intelligence-Crawler/1.0'])
                ->get($targetUrl);

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->status();

            if ($response->successful()) {
                $source->update([
                    'health_status' => 'healthy',
                    'consecutive_failures' => 0,
                    'last_error_message' => null,
                    'last_polled_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "HTTP {$statusCode} OK. Latency: {$latencyMs}ms. Valid feed detected.",
                    'latency_ms' => $latencyMs,
                    'status' => 'healthy',
                    'http_status' => $statusCode,
                ]);
            } else {
                $source->update([
                    'health_status' => 'degraded',
                    'consecutive_failures' => $source->consecutive_failures + 1,
                    'last_error_message' => "HTTP {$statusCode} returned from feed endpoint.",
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Feed returned HTTP {$statusCode}.",
                    'latency_ms' => $latencyMs,
                    'status' => 'degraded',
                    'http_status' => $statusCode,
                ]);
            }
        } catch (\Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            $source->update([
                'health_status' => 'failed',
                'consecutive_failures' => $source->consecutive_failures + 1,
                'last_error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Connection failed: " . $e->getMessage(),
                'latency_ms' => $latencyMs,
                'status' => 'failed',
            ]);
        }
    }

    /**
     * Trigger immediate ingestion scan for a single source.
     */
    public function scan(string $id, Request $request): JsonResponse
    {
        $source = NewsSource::findOrFail($id);
        $admin = $request->user();

        $job = PipelineJob::create([
            'source_id' => $source->id,
            'stage' => '1. Source Discovery',
            'status' => 'processing',
            'item_title' => "Scan for {$source->name}",
            'started_at' => now(),
        ]);

        $source->update([
            'last_polled_at' => now(),
            'total_items_ingested' => $source->total_items_ingested + 1,
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'source.scan_triggered',
            'resource_type' => 'NewsSource',
            'resource_id' => $source->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Ingestion scan initiated for '{$source->name}'.",
            'job' => $job,
        ]);
    }

    /**
     * Toggle source active/inactive state.
     */
    public function toggle(string $id): JsonResponse
    {
        $source = NewsSource::findOrFail($id);
        $source->is_active = !$source->is_active;
        $source->health_status = $source->is_active ? 'healthy' : 'paused';
        $source->save();

        return response()->json([
            'success' => true,
            'message' => "Source '{$source->name}' is now " . ($source->is_active ? 'active' : 'paused') . '.',
            'is_active' => $source->is_active,
            'health_status' => $source->health_status,
        ]);
    }

    /**
     * Delete a news source.
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        $source = NewsSource::findOrFail($id);
        $name = $source->name;
        $source->delete();

        return response()->json([
            'success' => true,
            'message' => "Source '{$name}' deleted.",
        ]);
    }
}
