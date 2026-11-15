<?php

declare(strict_types=1);

namespace App\Services\Workspace;

use App\Models\EntityRelationship;
use App\Models\IntelligenceEntity;
use App\Models\IntelligenceEvent;
use App\Models\SubscriberWatchlist;
use App\Models\SubscriberWatchlistItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WatchlistService
{
    /**
     * Retrieve all watchlists for a subscriber.
     */
    public function listWatchlists(User $user): Collection
    {
        return $user->watchlists()
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Retrieve a specific watchlist with items and connected entity graph.
     */
    public function getWatchlist(User $user, string $id): SubscriberWatchlist
    {
        $userOrgIds = $user->organizationMemberships()->pluck('organization_id')->filter()->all();

        $watchlist = SubscriberWatchlist::where('id', $id)
            ->where(function ($query) use ($user, $userOrgIds) {
                $query->where('user_id', $user->id);
                if (!empty($userOrgIds)) {
                    $query->orWhere(function ($q) use ($userOrgIds) {
                        $q->where('is_shared', true)
                          ->whereIn('organization_id', $userOrgIds);
                    });
                }
            })
            ->with(['items.intelligenceEntity'])
            ->firstOrFail();

        return $watchlist;
    }

    /**
     * Create a new watchlist enforcing plan entitlements.
     */
    public function createWatchlist(User $user, array $data): SubscriberWatchlist
    {
        $currentCount = $user->watchlists()->count();
        $limit = $this->getWatchlistLimit($user);

        if ($currentCount >= $limit) {
            throw ValidationException::withMessages([
                'name' => ["Your subscription tier allows a maximum of {$limit} watchlists. Upgrade your plan to create more."],
            ]);
        }

        $isDefault = !empty($data['is_default']) || $currentCount === 0;

        if ($isDefault) {
            $user->watchlists()->update(['is_default' => false]);
        }

        return $user->watchlists()->create([
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'is_default' => $isDefault,
            'notification_frequency' => $data['notification_frequency'] ?? 'daily',
            'sort_order' => $data['sort_order'] ?? ($currentCount + 1),
        ]);
    }

    /**
     * Update an existing watchlist.
     */
    public function updateWatchlist(User $user, string $id, array $data): SubscriberWatchlist
    {
        $watchlist = SubscriberWatchlist::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (!empty($data['is_default'])) {
            $user->watchlists()->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $watchlist->update([
            'name' => isset($data['name']) ? trim($data['name']) : $watchlist->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $watchlist->description,
            'is_default' => $data['is_default'] ?? $watchlist->is_default,
            'notification_frequency' => $data['notification_frequency'] ?? $watchlist->notification_frequency,
            'sort_order' => $data['sort_order'] ?? $watchlist->sort_order,
        ]);

        return $watchlist->fresh(['items']);
    }

    /**
     * Delete a watchlist.
     */
    public function deleteWatchlist(User $user, string $id): bool
    {
        $watchlist = SubscriberWatchlist::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $wasDefault = $watchlist->is_default;
        $deleted = (bool) $watchlist->delete();

        if ($wasDefault) {
            $first = $user->watchlists()->first();
            if ($first) {
                $first->update(['is_default' => true]);
            }
        }

        return $deleted;
    }

    /**
     * Add an entity item to a watchlist with duplicate prevention & entity resolution.
     */
    public function addItem(User $user, string $watchlistId, array $data): SubscriberWatchlistItem
    {
        $watchlist = SubscriberWatchlist::where('id', $watchlistId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $itemLimit = $this->getItemsPerWatchlistLimit($user);
        if ($watchlist->items()->count() >= $itemLimit) {
            throw ValidationException::withMessages([
                'entity_name' => ["This watchlist has reached its capacity limit of {$itemLimit} entities for your tier."],
            ]);
        }

        $entityName = trim($data['entity_name']);
        $entityType = strtolower(trim($data['entity_type'] ?? 'company'));

        // Resolve or associate IntelligenceEntity
        $entityId = $data['entity_id'] ?? null;
        if (!$entityId) {
            $entity = IntelligenceEntity::where('canonical_name', $entityName)
                ->orWhereHas('aliases', fn ($q) => $q->where('alias', $entityName))
                ->first();

            if ($entity) {
                $entityId = $entity->id;
                $entityName = $entity->canonical_name;
            }
        }

        return $watchlist->items()->firstOrCreate(
            [
                'entity_type' => $entityType,
                'entity_name' => $entityName,
            ],
            [
                'entity_id' => $entityId,
                'entity_slug' => Str::slug($entityName),
                'metadata' => $data['metadata'] ?? null,
            ]
        );
    }

    /**
     * Remove an item from a watchlist.
     */
    public function removeItem(User $user, string $watchlistId, string $itemId): bool
    {
        $watchlist = SubscriberWatchlist::where('id', $watchlistId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return (bool) $watchlist->items()->where('id', $itemId)->delete();
    }

    /**
     * Get historical event timeline for all entities in this watchlist.
     */
    public function getWatchlistTimeline(User $user, string $watchlistId, int $limit = 20): array
    {
        $watchlist = $this->getWatchlist($user, $watchlistId);
        $entityIds = $watchlist->items->pluck('entity_id')->filter()->values()->all();
        $entityNames = $watchlist->items->pluck('entity_name')->values()->all();

        $eventsQuery = IntelligenceEvent::query();

        if (!empty($entityIds)) {
            $eventsQuery->where(function ($q) use ($entityIds, $entityNames) {
                $q->whereIn('primary_entity_id', $entityIds)
                    ->orWhereHas('entities', fn ($sq) => $sq->whereIn('intelligence_entities.id', $entityIds))
                    ->orWhere(function ($sq) use ($entityNames) {
                        foreach ($entityNames as $name) {
                            $sq->orWhere('title', 'like', "%{$name}%")
                               ->orWhere('summary', 'like', "%{$name}%");
                        }
                    });
            });
        }

        return $eventsQuery->orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Discover connected entities through the relationship graph.
     */
    public function getConnectedEntities(User $user, string $watchlistId): array
    {
        $watchlist = $this->getWatchlist($user, $watchlistId);
        $entityIds = $watchlist->items->pluck('entity_id')->filter()->values()->all();

        if (empty($entityIds)) {
            return [];
        }

        $relationships = EntityRelationship::whereIn('source_entity_id', $entityIds)
            ->orWhereIn('target_entity_id', $entityIds)
            ->with(['sourceEntity', 'targetEntity'])
            ->limit(30)
            ->get();

        $connected = [];
        foreach ($relationships as $rel) {
            $isSource = in_array($rel->source_entity_id, $entityIds);
            $target = $isSource ? $rel->targetEntity : $rel->sourceEntity;
            if ($target && !in_array($target->id, $entityIds) && !isset($connected[$target->id])) {
                $connected[$target->id] = [
                    'id' => $target->id,
                    'name' => $target->canonical_name,
                    'entity_type' => $target->entity_type,
                    'relationship' => $rel->relationship_type,
                    'direction' => $isSource ? 'outbound' : 'inbound',
                    'confidence_score' => $rel->confidence_score,
                ];
            }
        }

        return array_values($connected);
    }

    /**
     * Configurable plan limits for watchlists.
     */
    protected function getWatchlistLimit(User $user): int
    {
        return app(\App\Services\Billing\EntitlementService::class)->getLimit($user, 'max_watchlists', 5);
    }

    /**
     * Configurable plan limits for items per watchlist.
     */
    protected function getItemsPerWatchlistLimit(User $user): int
    {
        return app(\App\Services\Billing\EntitlementService::class)->getLimit($user, 'max_entities_per_watchlist', 15);
    }
}
