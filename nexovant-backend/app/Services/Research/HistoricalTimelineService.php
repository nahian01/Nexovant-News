<?php

declare(strict_types=1);

namespace App\Services\Research;

use App\Models\DiscoveredStory;
use App\Models\IntelligenceEntity;
use App\Models\IntelligenceEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HistoricalTimelineService
{
    /**
     * Record a historical event with distinction between occurred_at (actual event date) and reported_at.
     *
     * @param array<string, mixed> $data
     */
    public function recordEvent(array $data): IntelligenceEvent
    {
        return DB::transaction(function () use ($data) {
            $title = trim($data['title']);
            $slug = Str::slug(substr($title, 0, 70));
            if (IntelligenceEvent::where('slug', $slug)->exists()) {
                $slug .= '-' . Str::lower(Str::random(5));
            }

            $event = IntelligenceEvent::create([
                'title' => $title,
                'slug' => $slug,
                'event_category' => $data['event_category'] ?? 'technology_breakthrough',
                'summary' => $data['summary'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? now(),
                'reported_at' => $data['reported_at'] ?? now(),
                'discovered_at' => now(),
                'verification_status' => $data['verification_status'] ?? 'reported_reliable',
                'primary_entity_id' => $data['primary_entity_id'] ?? null,
                'cluster_id' => $data['cluster_id'] ?? null,
                'article_id' => $data['article_id'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);

            // Attach associated entities
            if (!empty($data['entity_ids']) && is_array($data['entity_ids'])) {
                foreach ($data['entity_ids'] as $entityId) {
                    $event->entities()->attach($entityId, ['role' => 'associated_entity']);
                }
            } elseif (!empty($data['primary_entity_id'])) {
                $event->entities()->syncWithoutDetaching([$data['primary_entity_id'] => ['role' => 'subject']]);
            }

            return $event->load(['primaryEntity', 'entities']);
        });
    }

    /**
     * Retrieve chronological event timeline for a specific entity.
     *
     * @param array<string, mixed> $filters
     * @return Collection<int, IntelligenceEvent>
     */
    public function getTimelineForEntity(string $entityId, array $filters = []): Collection
    {
        $query = IntelligenceEvent::query()
            ->with(['primaryEntity', 'entities', 'cluster', 'article'])
            ->where(function ($q) use ($entityId) {
                $q->where('primary_entity_id', $entityId)
                  ->orWhereHas('entities', fn($sq) => $sq->where('intelligence_entities.id', $entityId));
            });

        if (!empty($filters['event_category'])) {
            $query->where('event_category', $filters['event_category']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('occurred_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('occurred_at', '<=', $filters['end_date']);
        }

        return $query->orderBy('occurred_at', 'asc')->get();
    }

    /**
     * Retrieve global chronological timeline across all entities.
     *
     * @param array<string, mixed> $filters
     * @return Collection<int, IntelligenceEvent>
     */
    public function getGlobalTimeline(array $filters = []): Collection
    {
        $query = IntelligenceEvent::query()
            ->with(['primaryEntity', 'entities', 'cluster']);

        if (!empty($filters['event_category'])) {
            $query->where('event_category', $filters['event_category']);
        }

        if (!empty($filters['verification_status'])) {
            $query->where('verification_status', $filters['verification_status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                  ->orWhere('summary', 'like', $search);
            });
        }

        return $query->orderBy('occurred_at', 'desc')->limit($filters['limit'] ?? 50)->get();
    }

    /**
     * Correct the occurred_at date of an event with full audit recording.
     */
    public function correctEventDate(
        IntelligenceEvent $event,
        string $newOccurredAt,
        string $reason,
        ?string $userId = null
    ): IntelligenceEvent {
        $oldOccurredAt = $event->occurred_at?->toIso8601String();

        $meta = $event->metadata ?? [];
        $meta['date_corrections'] = $meta['date_corrections'] ?? [];
        $meta['date_corrections'][] = [
            'previous_date' => $oldOccurredAt,
            'corrected_to' => $newOccurredAt,
            'reason' => $reason,
            'user_id' => $userId,
            'corrected_at' => now()->toIso8601String(),
        ];

        $event->update([
            'occurred_at' => $newOccurredAt,
            'metadata' => $meta,
        ]);

        return $event;
    }
}
