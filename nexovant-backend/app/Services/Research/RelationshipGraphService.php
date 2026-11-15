<?php

declare(strict_types=1);

namespace App\Services\Research;

use App\Models\EntityRelationship;
use App\Models\IntelligenceEntity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RelationshipGraphService
{
    /**
     * Record a new relationship between two entities with supporting evidence.
     *
     * @param array<string, mixed> $data
     */
    public function createRelationship(array $data): EntityRelationship
    {
        return EntityRelationship::create([
            'source_entity_id' => $data['source_entity_id'],
            'relationship_type' => $data['relationship_type'],
            'target_entity_id' => $data['target_entity_id'],
            'supporting_source_id' => $data['supporting_source_id'] ?? null,
            'story_id' => $data['story_id'] ?? null,
            'article_id' => $data['article_id'] ?? null,
            'evidence_excerpt' => $data['evidence_excerpt'] ?? null,
            'source_url' => $data['source_url'] ?? null,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_to' => $data['valid_to'] ?? null,
            'confidence_score' => $data['confidence_score'] ?? 0.85,
            'verification_status' => $data['verification_status'] ?? 'single_source',
            'is_active' => $data['is_active'] ?? true,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    /**
     * Retrieve direct relationships for an entity with optional filtering.
     *
     * @param array<string, mixed> $filters
     * @return Collection<int, EntityRelationship>
     */
    public function getDirectRelationships(string $entityId, array $filters = []): Collection
    {
        $query = EntityRelationship::query()
            ->with(['sourceEntity', 'targetEntity', 'supportingSource'])
            ->where(function ($q) use ($entityId) {
                $q->where('source_entity_id', $entityId)
                  ->orWhere('target_entity_id', $entityId);
            });

        if (!empty($filters['relationship_type'])) {
            $query->where('relationship_type', $filters['relationship_type']);
        }

        if (!empty($filters['verification_status'])) {
            $query->where('verification_status', $filters['verification_status']);
        }

        if (isset($filters['min_confidence'])) {
            $query->where('confidence_score', '>=', (float) $filters['min_confidence']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->orderByDesc('confidence_score')->get();
    }

    /**
     * Build an interactive multi-level graph (nodes and links) for network visualization.
     *
     * @param array<string, mixed> $filters
     * @return array{nodes: array<int, array<string, mixed>>, links: array<int, array<string, mixed>>, metadata: array<string, mixed>}
     */
    public function getMultiLevelGraph(string $rootEntityId, int $depth = 2, array $filters = []): array
    {
        $visitedEntityIds = [$rootEntityId => true];
        $currentFrontier = [$rootEntityId];
        $allRelationships = collect();

        for ($d = 1; $d <= min($depth, 3); $d++) {
            if (empty($currentFrontier)) {
                break;
            }

            $query = EntityRelationship::query()
                ->with(['sourceEntity', 'targetEntity', 'supportingSource'])
                ->where(function ($q) use ($currentFrontier) {
                    $q->whereIn('source_entity_id', $currentFrontier)
                      ->orWhereIn('target_entity_id', $currentFrontier);
                });

            if (!empty($filters['relationship_type'])) {
                $query->where('relationship_type', $filters['relationship_type']);
            }

            if (!empty($filters['verification_status'])) {
                $query->where('verification_status', $filters['verification_status']);
            }

            if (isset($filters['min_confidence'])) {
                $query->where('confidence_score', '>=', (float) $filters['min_confidence']);
            }

            $rels = $query->get();
            $nextFrontier = [];

            foreach ($rels as $r) {
                $allRelationships->put($r->id, $r);

                if (!isset($visitedEntityIds[$r->source_entity_id])) {
                    $visitedEntityIds[$r->source_entity_id] = true;
                    $nextFrontier[] = $r->source_entity_id;
                }
                if (!isset($visitedEntityIds[$r->target_entity_id])) {
                    $visitedEntityIds[$r->target_entity_id] = true;
                    $nextFrontier[] = $r->target_entity_id;
                }
            }

            $currentFrontier = $nextFrontier;
        }

        // Fetch all collected node entities
        $entities = IntelligenceEntity::whereIn('id', array_keys($visitedEntityIds))->get()->keyBy('id');

        $nodes = [];
        foreach ($entities as $e) {
            $nodes[] = [
                'id' => $e->id,
                'name' => $e->canonical_name,
                'type' => $e->entity_type,
                'verification_status' => $e->verification_status,
                'confidence_score' => $e->confidence_score,
                'is_root' => $e->id === $rootEntityId,
            ];
        }

        $links = [];
        foreach ($allRelationships as $rel) {
            $links[] = [
                'id' => $rel->id,
                'source' => $rel->source_entity_id,
                'target' => $rel->target_entity_id,
                'type' => $rel->relationship_type,
                'verification_status' => $rel->verification_status,
                'confidence_score' => $rel->confidence_score,
                'evidence' => $rel->evidence_excerpt,
                'source_name' => $rel->supportingSource?->name ?? 'Direct reporting',
                'source_url' => $rel->source_url,
                'is_active' => $rel->is_active,
            ];
        }

        return [
            'nodes' => $nodes,
            'links' => $links,
            'metadata' => [
                'root_entity_id' => $rootEntityId,
                'total_nodes' => count($nodes),
                'total_links' => count($links),
                'depth' => $depth,
            ],
        ];
    }

    /**
     * Verify a relationship as confirmed by human editorial review.
     */
    public function verifyRelationship(EntityRelationship $relationship, ?string $userId = null): EntityRelationship
    {
        $relationship->update([
            'verification_status' => 'verified',
            'metadata' => array_merge($relationship->metadata ?? [], [
                'verified_by_user_id' => $userId,
                'verified_at' => now()->toIso8601String(),
            ]),
        ]);

        return $relationship;
    }

    /**
     * Mark a relationship as disputed.
     */
    public function disputeRelationship(EntityRelationship $relationship, string $reason, ?string $userId = null): EntityRelationship
    {
        $relationship->update([
            'verification_status' => 'disputed',
            'metadata' => array_merge($relationship->metadata ?? [], [
                'disputed_by_user_id' => $userId,
                'disputed_at' => now()->toIso8601String(),
                'dispute_reason' => $reason,
            ]),
        ]);

        return $relationship;
    }
}
