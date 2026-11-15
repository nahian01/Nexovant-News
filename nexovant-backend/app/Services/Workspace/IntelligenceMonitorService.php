<?php

declare(strict_types=1);

namespace App\Services\Workspace;

use App\Models\Article;
use App\Models\IntelligenceEvent;
use App\Models\StoryCluster;
use App\Models\SubscriberMonitor;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class IntelligenceMonitorService
{
    public function __construct(
        protected SubscriberNotificationService $notificationService
    ) {}

    /**
     * List all monitors for a user.
     */
    public function listMonitors(User $user): Collection
    {
        return $user->monitors()->orderByDesc('created_at')->get();
    }

    /**
     * Get single monitor.
     */
    public function getMonitor(User $user, string $id): SubscriberMonitor
    {
        return SubscriberMonitor::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    /**
     * Create a monitor enforcing plan entitlements.
     */
    public function createMonitor(User $user, array $data): SubscriberMonitor
    {
        $currentCount = $user->monitors()->where('is_active', true)->count();
        $limit = $this->getMonitorsLimit($user);

        if ($currentCount >= $limit) {
            throw ValidationException::withMessages([
                'name' => ["Your subscription tier allows a maximum of {$limit} active monitors. Upgrade your plan to activate more."],
            ]);
        }

        $keywords = array_filter(array_map('trim', (array) ($data['keywords'] ?? [])));
        if (empty($keywords)) {
            throw ValidationException::withMessages([
                'keywords' => ['At least one search keyword or monitoring term is required.'],
            ]);
        }

        return $user->monitors()->create([
            'name' => trim($data['name']),
            'keywords' => array_values($keywords),
            'excluded_terms' => array_values(array_filter(array_map('trim', (array) ($data['excluded_terms'] ?? [])))),
            'entity_names' => array_values(array_filter(array_map('trim', (array) ($data['entity_names'] ?? [])))),
            'categories' => array_values(array_filter(array_map('trim', (array) ($data['categories'] ?? [])))),
            'industries' => array_values(array_filter(array_map('trim', (array) ($data['industries'] ?? [])))),
            'regions' => array_values(array_filter(array_map('trim', (array) ($data['regions'] ?? [])))),
            'min_credibility_score' => $data['min_credibility_score'] ?? 0.70,
            'require_verified_only' => !empty($data['require_verified_only']),
            'alert_frequency' => $data['alert_frequency'] ?? 'real_time',
            'delivery_channels' => $data['delivery_channels'] ?? ['in_app'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update an existing monitor.
     */
    public function updateMonitor(User $user, string $id, array $data): SubscriberMonitor
    {
        $monitor = $this->getMonitor($user, $id);

        $keywords = isset($data['keywords']) ? array_filter(array_map('trim', (array) $data['keywords'])) : $monitor->keywords;

        $monitor->update([
            'name' => isset($data['name']) ? trim($data['name']) : $monitor->name,
            'keywords' => array_values($keywords),
            'excluded_terms' => isset($data['excluded_terms']) ? array_values(array_filter(array_map('trim', (array) $data['excluded_terms']))) : $monitor->excluded_terms,
            'entity_names' => isset($data['entity_names']) ? array_values(array_filter(array_map('trim', (array) $data['entity_names']))) : $monitor->entity_names,
            'categories' => isset($data['categories']) ? array_values(array_filter(array_map('trim', (array) $data['categories']))) : $monitor->categories,
            'industries' => isset($data['industries']) ? array_values(array_filter(array_map('trim', (array) $data['industries']))) : $monitor->industries,
            'regions' => isset($data['regions']) ? array_values(array_filter(array_map('trim', (array) $data['regions']))) : $monitor->regions,
            'min_credibility_score' => $data['min_credibility_score'] ?? $monitor->min_credibility_score,
            'require_verified_only' => isset($data['require_verified_only']) ? (bool) $data['require_verified_only'] : $monitor->require_verified_only,
            'alert_frequency' => $data['alert_frequency'] ?? $monitor->alert_frequency,
            'delivery_channels' => $data['delivery_channels'] ?? $monitor->delivery_channels,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $monitor->is_active,
        ]);

        return $monitor->fresh();
    }

    /**
     * Toggle active state.
     */
    public function toggleMonitor(User $user, string $id): SubscriberMonitor
    {
        $monitor = $this->getMonitor($user, $id);
        $monitor->update(['is_active' => !$monitor->is_active]);
        return $monitor->fresh();
    }

    /**
     * Delete a monitor.
     */
    public function deleteMonitor(User $user, string $id): bool
    {
        $monitor = $this->getMonitor($user, $id);
        return (bool) $monitor->delete();
    }

    /**
     * Evaluate an incoming intelligence record against all active monitors.
     */
    public function matchAndAlert(Model $item, string $itemType): int
    {
        $activeMonitors = SubscriberMonitor::where('is_active', true)->with('user')->get();
        $alertCount = 0;

        foreach ($activeMonitors as $monitor) {
            if ($this->doesItemMatchMonitor($item, $itemType, $monitor)) {
                $this->dispatchMonitorAlert($monitor, $item, $itemType);
                $monitor->increment('match_count');
                $monitor->update(['last_triggered_at' => now()]);
                $alertCount++;
            }
        }

        return $alertCount;
    }

    /**
     * Test a rule set against recent platform items to preview matches.
     */
    public function previewMatches(User $user, array $data): array
    {
        $tempMonitor = new SubscriberMonitor($data);
        $tempMonitor->user_id = $user->id;

        $recentArticles = Article::latest('published_at')->limit(30)->get();
        $matches = [];

        foreach ($recentArticles as $article) {
            if ($this->doesItemMatchMonitor($article, 'article', $tempMonitor)) {
                $matches[] = [
                    'id' => $article->id,
                    'title' => $article->title,
                    'published_at' => $article->published_at?->toIso8601String(),
                    'type' => 'article',
                ];
            }
        }

        return $matches;
    }

    /**
     * Evaluate rule conditions against item text and metadata.
     */
    protected function doesItemMatchMonitor(Model $item, string $itemType, SubscriberMonitor $monitor): bool
    {
        $title = strtolower($item->title ?? $item->headline ?? '');
        $content = strtolower($item->content_normalized ?? $item->summary ?? $item->content_raw ?? '');
        $combinedText = "{$title} {$content}";

        // 1. Excluded terms check: If any excluded term appears, immediate rejection
        if (!empty($monitor->excluded_terms)) {
            foreach ($monitor->excluded_terms as $term) {
                if (!empty($term) && str_contains($combinedText, strtolower($term))) {
                    return false;
                }
            }
        }

        // 2. Verification requirement
        if ($monitor->require_verified_only) {
            $status = $item->verification_status ?? 'unverified';
            if (!in_array($status, ['verified', 'confirmed', 'supported_multiple', 'reported_reliable'])) {
                return false;
            }
        }

        // 3. Keyword check (must match at least one keyword)
        $keywordMatched = false;
        if (!empty($monitor->keywords)) {
            foreach ($monitor->keywords as $kw) {
                if (!empty($kw) && str_contains($combinedText, strtolower($kw))) {
                    $keywordMatched = true;
                    break;
                }
            }
        } else {
            $keywordMatched = true;
        }

        if (!$keywordMatched) {
            return false;
        }

        // 4. Entity names check (if specified, must match at least one)
        if (!empty($monitor->entity_names)) {
            $entityMatched = false;
            foreach ($monitor->entity_names as $entity) {
                if (!empty($entity) && str_contains($combinedText, strtolower($entity))) {
                    $entityMatched = true;
                    break;
                }
            }
            if (!$entityMatched) {
                return false;
            }
        }

        return true;
    }

    /**
     * Dispatch notification for a matched monitor.
     */
    protected function dispatchMonitorAlert(SubscriberMonitor $monitor, Model $item, string $itemType): void
    {
        $title = $item->title ?? $item->headline ?? 'Verified Intelligence Alert';
        $link = match ($itemType) {
            'article' => "/news/{$item->slug}",
            'event' => "/trends",
            'cluster' => "/feed",
            default => "/dashboard",
        };

        $channels = $monitor->delivery_channels ?: ['in_app'];
        $primaryChannel = in_array('email', $channels) ? 'email' : 'in_app';

        $this->notificationService->sendNotification($monitor->user, [
            'category' => 'monitor_alert',
            'title' => "Custom Monitor Triggered: {$monitor->name}",
            'message' => "New matching development detected: \"{$title}\"",
            'link' => $link,
            'delivery_channel' => $primaryChannel,
        ]);
    }

    /**
     * Configurable plan limit for monitors.
     */
    protected function getMonitorsLimit(User $user): int
    {
        return app(\App\Services\Billing\EntitlementService::class)->getLimit($user, 'max_active_monitors', 3);
    }
}
