<?php

declare(strict_types=1);

namespace App\Services\Discovery;

use App\Models\Article;
use App\Models\SubscriberSavedSearch;
use App\Models\User;
use App\Services\Notification\DTO\NotificationEventDTO;
use App\Services\Notification\NotificationEngine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SavedSearchService
{
    public function __construct(
        protected NotificationEngine $notificationEngine
    ) {}

    /**
     * List all saved searches for a user.
     *
     * @return Collection<int, SubscriberSavedSearch>
     */
    public function listSavedSearches(User $user): Collection
    {
        return SubscriberSavedSearch::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Create a new saved search.
     *
     * @param array<string, mixed> $data
     */
    public function createSavedSearch(User $user, array $data): SubscriberSavedSearch
    {
        $name = trim((string) ($data['name'] ?? ''));
        $query = trim((string) ($data['query'] ?? ''));

        if ($name === '' || $query === '') {
            throw ValidationException::withMessages([
                'name' => ['Name and search query are required.'],
            ]);
        }

        return SubscriberSavedSearch::create([
            'user_id' => $user->id,
            'organization_id' => $user->organization_id ?? null,
            'name' => $name,
            'description' => $data['description'] ?? null,
            'query' => $query,
            'filters' => $data['filters'] ?? [],
            'alert_frequency' => $data['alert_frequency'] ?? 'daily_digest',
            'delivery_channels' => $data['delivery_channels'] ?? ['in_app'],
            'is_active' => $data['is_active'] ?? true,
            'last_executed_at' => now(),
        ]);
    }

    /**
     * Update an existing saved search.
     *
     * @param array<string, mixed> $data
     */
    public function updateSavedSearch(User $user, string $id, array $data): SubscriberSavedSearch
    {
        $savedSearch = SubscriberSavedSearch::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $savedSearch->update([
            'name' => isset($data['name']) ? trim((string) $data['name']) : $savedSearch->name,
            'description' => $data['description'] ?? $savedSearch->description,
            'query' => isset($data['query']) ? trim((string) $data['query']) : $savedSearch->query,
            'filters' => $data['filters'] ?? $savedSearch->filters,
            'alert_frequency' => $data['alert_frequency'] ?? $savedSearch->alert_frequency,
            'delivery_channels' => $data['delivery_channels'] ?? $savedSearch->delivery_channels,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $savedSearch->is_active,
        ]);

        return $savedSearch->fresh();
    }

    /**
     * Toggle active/monitoring status.
     */
    public function toggleSavedSearch(User $user, string $id): SubscriberSavedSearch
    {
        $savedSearch = SubscriberSavedSearch::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $savedSearch->update([
            'is_active' => !$savedSearch->is_active,
        ]);

        return $savedSearch->fresh();
    }

    /**
     * Delete a saved search.
     */
    public function deleteSavedSearch(User $user, string $id): bool
    {
        $savedSearch = SubscriberSavedSearch::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return (bool) $savedSearch->delete();
    }

    /**
     * Preview matching items for a saved search.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMatches(SubscriberSavedSearch $savedSearch, int $limit = 10): array
    {
        $queryText = $savedSearch->query;
        $articles = Article::with('source')
            ->where(function ($q) use ($queryText) {
                $q->where('title', 'like', "%{$queryText}%")
                  ->orWhere('content_normalized', 'like', "%{$queryText}%");
            })
            ->latest('published_at')
            ->limit($limit)
            ->get();

        $matches = [];
        foreach ($articles as $a) {
            $matches[] = [
                'id' => $a->id,
                'title' => $a->title,
                'source' => $a->source?->name ?? 'Publisher',
                'published_at' => $a->published_at?->toIso8601String(),
                'url' => "/news/{$a->slug}",
            ];
        }

        $savedSearch->update([
            'last_executed_at' => now(),
            'match_count' => count($matches),
        ]);

        return $matches;
    }

    /**
     * Match newly ingested intelligence item against active saved searches and alert subscribers.
     */
    public function evaluateItemAndAlert(Model $item, string $itemType = 'article'): int
    {
        $activeSearches = SubscriberSavedSearch::where('is_active', true)
            ->where('alert_frequency', '!=', 'disabled')
            ->with('user')
            ->get();

        $title = strtolower($item->title ?? $item->canonical_title ?? '');
        $content = strtolower($item->content_normalized ?? $item->summary ?? '');
        $combined = "{$title} {$content}";

        $dispatched = 0;
        foreach ($activeSearches as $search) {
            $searchTerm = strtolower($search->query);
            if (!empty($searchTerm) && str_contains($combined, $searchTerm)) {
                $this->dispatchSearchAlert($search, $item, $itemType);
                $search->increment('match_count');
                $search->update(['last_matched_at' => now()]);
                $dispatched++;
            }
        }

        return $dispatched;
    }

    /**
     * Dispatch notification via Stage 11 central NotificationEngine.
     */
    protected function dispatchSearchAlert(SubscriberSavedSearch $search, Model $item, string $itemType): void
    {
        $itemTitle = $item->title ?? $item->canonical_title ?? 'New Intelligence Development';
        $itemUrl = $itemType === 'article' ? "/news/{$item->slug}" : "/feed";

        $dto = new NotificationEventDTO(
            eventType: 'saved_search_alert',
            sourceModule: 'discovery',
            recipientIds: [$search->user_id],
            title: "Saved Search Match: {$search->name}",
            message: "New intelligence matching '{$search->query}': \"{$itemTitle}\"",
            link: $itemUrl,
            priority: 'medium',
            metadata: [
                'saved_search_id' => $search->id,
                'query' => $search->query,
                'item_type' => $itemType,
                'item_id' => $item->id,
            ],
            tenantId: $search->organization_id,
            category: 'monitor_alert'
        );

        $this->notificationEngine->dispatch($dto);
    }
}
