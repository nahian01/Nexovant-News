<?php

declare(strict_types=1);

namespace App\Services\Discovery;

use App\Models\Article;
use App\Models\EntityRelationship;
use App\Models\IntelligenceEntity;
use App\Models\IntelligenceEvent;
use App\Models\ResearchReport;
use App\Models\SubscriberWatchlistItem;
use App\Services\Research\RelationshipGraphService;
use Illuminate\Support\Facades\DB;

class EntityDiscoveryService
{
    public function __construct(
        protected RelationshipGraphService $graphService
    ) {}

    /**
     * Get enriched entity profile for discovery explorer.
     *
     * @return array<string, mixed>
     */
    public function getEntityProfile(string $slugOrId, ?string $userId = null): array
    {
        $entity = IntelligenceEntity::where('id', $slugOrId)
            ->orWhere('slug', $slugOrId)
            ->orWhere('canonical_name', $slugOrId)
            ->with(['aliases', 'events' => fn($q) => $q->orderByDesc('occurred_at')->limit(8)])
            ->firstOrFail();

        // 1. Relationships categorized as verified vs AI-inferred
        $directRelationships = EntityRelationship::with(['sourceEntity', 'targetEntity', 'supportingSource'])
            ->where(function ($q) use ($entity) {
                $q->where('source_entity_id', $entity->id)
                  ->orWhere('target_entity_id', $entity->id);
            })
            ->where('is_active', true)
            ->get();

        $verifiedRelationships = [];
        $inferredRelationships = [];

        foreach ($directRelationships as $rel) {
            $otherEntity = $rel->source_entity_id === $entity->id ? $rel->targetEntity : $rel->sourceEntity;
            if (!$otherEntity) continue;

            $relItem = [
                'relationship_id' => $rel->id,
                'type' => $rel->relationship_type,
                'related_entity' => [
                    'id' => $otherEntity->id,
                    'name' => $otherEntity->canonical_name,
                    'slug' => $otherEntity->slug,
                    'type' => $otherEntity->entity_type,
                ],
                'direction' => $rel->source_entity_id === $entity->id ? 'outgoing' : 'incoming',
                'confidence_score' => $rel->confidence_score,
                'verification_status' => $rel->verification_status,
                'evidence_excerpt' => $rel->evidence_excerpt,
                'source' => $rel->supportingSource?->name,
                'is_verified' => in_array($rel->verification_status, ['verified', 'confirmed', 'multi_source'], true),
            ];

            if ($relItem['is_verified']) {
                $verifiedRelationships[] = $relItem;
            } else {
                $inferredRelationships[] = $relItem;
            }
        }

        // 2. Related Articles & Stories
        $articles = Article::with(['source', 'categories'])
            ->where(function ($q) use ($entity) {
                $q->where('title', 'like', "%{$entity->canonical_name}%")
                  ->orWhere('content_normalized', 'like', "%{$entity->canonical_name}%")
                  ->orWhereHas('companies', fn($cq) => $cq->where('name', $entity->canonical_name));
            })
            ->latest('published_at')
            ->limit(6)
            ->get();

        // 3. Related Research Reports
        $reports = ResearchReport::where('is_public', true)
            ->where(function ($q) use ($entity) {
                $q->where('title', 'like', "%{$entity->canonical_name}%")
                  ->orWhere('executive_summary', 'like', "%{$entity->canonical_name}%");
            })
            ->latest('created_at')
            ->limit(4)
            ->get(['id', 'title', 'slug', 'executive_summary', 'confidence_score', 'uncertainty_rating', 'created_at']);

        // 4. Chronological Timeline
        $timeline = IntelligenceEvent::where(function ($q) use ($entity) {
                $q->where('primary_entity_id', $entity->id)
                  ->orWhereHas('entities', fn($eq) => $eq->where('intelligence_entities.id', $entity->id));
            })
            ->orderBy('occurred_at', 'desc')
            ->limit(10)
            ->get();

        // 5. Watchlist / Monitored Status for current user
        $isMonitored = false;
        if ($userId) {
            $isMonitored = SubscriberWatchlistItem::whereHas('watchlist', fn($wq) => $wq->where('user_id', $userId))
                ->where(function ($q) use ($entity) {
                    $q->where('entity_id', $entity->id)
                      ->orWhere('entity_name', $entity->canonical_name);
                })
                ->exists();
        }

        // 6. Interactive Network Graph (depth 2)
        $graph = $this->graphService->getMultiLevelGraph($entity->id, depth: 2);

        return [
            'entity' => [
                'id' => $entity->id,
                'canonical_name' => $entity->canonical_name,
                'slug' => $entity->slug,
                'legal_name' => $entity->legal_name,
                'entity_type' => $entity->entity_type,
                'description' => $entity->description,
                'website_url' => $entity->website_url,
                'country' => $entity->country,
                'headquarters' => $entity->headquarters,
                'industry' => $entity->industry,
                'founding_year' => $entity->founding_year,
                'verification_status' => $entity->verification_status,
                'aliases' => $entity->aliases->pluck('alias')->all(),
                'is_monitored' => $isMonitored,
            ],
            'relationships' => [
                'verified' => $verifiedRelationships,
                'verified_count' => count($verifiedRelationships),
                'inferred' => $inferredRelationships,
                'inferred_count' => count($inferredRelationships),
                'total_count' => count($directRelationships),
            ],
            'graph' => $graph,
            'related_stories' => $articles,
            'related_reports' => $reports,
            'timeline' => $timeline,
        ];
    }
}
