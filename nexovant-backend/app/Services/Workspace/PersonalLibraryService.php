<?php

declare(strict_types=1);

namespace App\Services\Workspace;

use App\Models\Article;
use App\Models\IntelligenceEvent;
use App\Models\Publication;
use App\Models\ResearchReport;
use App\Models\SubscriberCollection;
use App\Models\SubscriberLibraryItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersonalLibraryService
{
    /**
     * List saved library items with filtering and polymorphic item resolution.
     */
    public function listItems(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = SubscriberLibraryItem::where('user_id', $user->id)
            ->with('collection');

        if (!empty($filters['collection_id'])) {
            $query->where('collection_id', $filters['collection_id']);
        }

        if (!empty($filters['item_type']) && $filters['item_type'] !== 'all') {
            $query->where('item_type', $filters['item_type']);
        }

        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('private_notes', 'like', "%{$s}%")
                  ->orWhereJsonContains('tags', $s);
            });
        }

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        // Resolve underlying titles and summaries for each item
        $paginator->getCollection()->transform(function (SubscriberLibraryItem $item) {
            $resolved = $item->resolveResource();
            $item->setAttribute('title', $resolved?->title ?? $resolved?->headline ?? 'Saved Intelligence Record');
            $item->setAttribute('subtitle', $resolved?->subtitle ?? $resolved?->summary ?? null);
            $item->setAttribute('resource_slug', $resolved?->slug ?? null);
            $item->setAttribute('resource_date', $resolved?->published_at ?? $resolved?->occurred_at ?? $resolved?->created_at);
            return $item;
        });

        return $paginator;
    }

    /**
     * Save an item into user's personal knowledge library.
     */
    public function saveItem(User $user, array $data): SubscriberLibraryItem
    {
        $itemType = strtolower(trim($data['item_type']));
        $itemId = trim($data['item_id']);

        if (!in_array($itemType, ['article', 'publication', 'research_report', 'intelligence_event'])) {
            throw ValidationException::withMessages([
                'item_type' => ['Invalid intelligence record type.'],
            ]);
        }

        // Validate existence of underlying item
        $exists = match ($itemType) {
            'article' => Article::where('id', $itemId)->exists(),
            'publication' => Publication::where('id', $itemId)->exists(),
            'research_report' => ResearchReport::where('id', $itemId)->exists(),
            'intelligence_event' => IntelligenceEvent::where('id', $itemId)->exists(),
            default => false,
        };

        if (!$exists) {
            throw ValidationException::withMessages([
                'item_id' => ['The specified intelligence record does not exist.'],
            ]);
        }

        $libraryItem = SubscriberLibraryItem::firstOrCreate(
            [
                'user_id' => $user->id,
                'item_type' => $itemType,
                'item_id' => $itemId,
            ],
            [
                'collection_id' => $data['collection_id'] ?? null,
                'tags' => array_values(array_filter((array) ($data['tags'] ?? []))),
                'private_notes' => $data['private_notes'] ?? null,
            ]
        );

        if (!empty($data['private_notes']) || !empty($data['collection_id'])) {
            $libraryItem->update([
                'collection_id' => $data['collection_id'] ?? $libraryItem->collection_id,
                'tags' => isset($data['tags']) ? array_values(array_filter((array) $data['tags'])) : $libraryItem->tags,
                'private_notes' => $data['private_notes'] ?? $libraryItem->private_notes,
            ]);
        }

        return $libraryItem->fresh(['collection']);
    }

    /**
     * Update private notes or collection on a library item.
     */
    public function updateItem(User $user, string $id, array $data): SubscriberLibraryItem
    {
        $item = SubscriberLibraryItem::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $item->update([
            'collection_id' => array_key_exists('collection_id', $data) ? $data['collection_id'] : $item->collection_id,
            'tags' => isset($data['tags']) ? array_values(array_filter((array) $data['tags'])) : $item->tags,
            'private_notes' => array_key_exists('private_notes', $data) ? $data['private_notes'] : $item->private_notes,
        ]);

        return $item->fresh(['collection']);
    }

    /**
     * Remove an item from the library.
     */
    public function removeItem(User $user, string $id): bool
    {
        $item = SubscriberLibraryItem::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return (bool) $item->delete();
    }

    /**
     * List user collections.
     */
    public function listCollections(User $user): Collection
    {
        return $user->collections()->withCount('items')->orderBy('name')->get();
    }

    /**
     * Create a personal collection.
     */
    public function createCollection(User $user, array $data): SubscriberCollection
    {
        return $user->collections()->create([
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? '#d4af37',
        ]);
    }

    /**
     * Update a collection.
     */
    public function updateCollection(User $user, string $id, array $data): SubscriberCollection
    {
        $collection = SubscriberCollection::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $collection->update([
            'name' => isset($data['name']) ? trim($data['name']) : $collection->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $collection->description,
            'color' => $data['color'] ?? $collection->color,
        ]);

        return $collection->fresh();
    }

    /**
     * Delete a collection.
     */
    public function deleteCollection(User $user, string $id): bool
    {
        $collection = SubscriberCollection::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Nullify foreign keys on items
        $collection->items()->update(['collection_id' => null]);

        return (bool) $collection->delete();
    }

    /**
     * Export collection in structured format.
     */
    public function exportCollection(User $user, string $id, string $format = 'json'): array|string
    {
        $collection = SubscriberCollection::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['items'])
            ->firstOrFail();

        $exportedItems = [];
        foreach ($collection->items as $item) {
            $res = $item->resolveResource();
            $exportedItems[] = [
                'type' => $item->item_type,
                'title' => $res?->title ?? $res?->headline ?? 'Untitled Record',
                'tags' => $item->tags,
                'notes' => $item->private_notes,
                'saved_at' => $item->created_at?->toIso8601String(),
            ];
        }

        $payload = [
            'collection_name' => $collection->name,
            'description' => $collection->description,
            'exported_at' => now()->toIso8601String(),
            'total_items' => count($exportedItems),
            'items' => $exportedItems,
        ];

        if ($format === 'markdown') {
            $md = "# Collection: {$collection->name}\n\n";
            $md .= ">{$collection->description}\n\n";
            $md .= "Exported: " . now()->toFormattedDateString() . "\n\n---\n\n";
            foreach ($exportedItems as $idx => $it) {
                $num = $idx + 1;
                $md .= "### {$num}. [{$it['type']}] {$it['title']}\n";
                if (!empty($it['notes'])) {
                    $md .= "**Private Notes**: {$it['notes']}\n\n";
                }
                if (!empty($it['tags'])) {
                    $md .= "**Tags**: " . implode(', ', $it['tags']) . "\n\n";
                }
            }
            return $md;
        }

        return $payload;
    }
}
