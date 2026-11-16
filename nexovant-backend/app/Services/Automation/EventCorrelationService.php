<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\Article;
use App\Models\CorrelatedEvent;
use App\Models\IntelligenceEvent;
use Illuminate\Support\Collection;

class EventCorrelationService
{
    /**
     * Correlate a newly observed article/event with historical intelligence developments.
     * Strictly preserves correlation != causation boundaries.
     *
     * @param Article $article
     * @param string|null $tenantId
     * @return Collection<int, CorrelatedEvent>
     */
    public function correlateArticleEvents(Article $article, ?string $tenantId = null): Collection
    {
        $correlations = collect();
        $primaryTitle = $article->title;
        $primaryId = (string) $article->id;

        // 1. Entity-based & Topic-based correlation query
        $entityNames = $article->companies->pluck('name')->all();
        $words = array_filter(
            explode(' ', preg_replace('/[^a-zA-Z0-9 ]/', '', strtolower($article->title . ' ' . ($article->content_normalized ?? '')))),
            fn($w) => strlen($w) >= 4
        );

        $query = IntelligenceEvent::query()
            ->where('id', '!=', $primaryId)
            ->where(function ($q) use ($entityNames, $words, $article) {
                if (!empty($entityNames)) {
                    foreach ($entityNames as $name) {
                        $q->orWhere('title', 'like', "%{$name}%");
                    }
                }
                foreach ($words as $word) {
                    $root = substr($word, 0, min(strlen($word), 5));
                    $q->orWhere('title', 'like', "%{$root}%");
                }
                $q->orWhere('title', 'like', "%{$article->title}%");
            })
            ->latest('occurred_at')
            ->limit(5);

        $events = $query->get();

        foreach ($events as $event) {
            $correlationType = $this->determineCorrelationType($article, $event);
            $strength = $this->calculateCorrelationStrength($article, $event);

            $alternativeExplanations = "Parallel industry-wide macroeconomic pressure, localized supply chain friction, or independent commercial cycle timing may account for concurrent developments.";
            $unresolvedQuestions = "Direct corporate coordination or formal causal relationship cannot be substantiated without official regulatory disclosures or executed agreements.";

            $correlated = CorrelatedEvent::create([
                'primary_event_title' => $primaryTitle,
                'primary_event_id' => $primaryId,
                'correlated_event_title' => $event->title,
                'correlated_event_id' => (string) $event->id,
                'correlation_type' => $correlationType,
                'correlation_strength' => $strength,
                'is_causation_claimed' => false, // STRICT GOVERNANCE: Always false
                'evidence_summary' => "Observed co-occurrence between [{$primaryTitle}] and historical milestone [{$event->title}]. Both touch common operational or structural factors.",
                'supporting_sources' => [
                    'primary_source' => $article->source?->name ?? 'Wire',
                    'correlated_source' => $event->metadata['source_name'] ?? 'Historical Record',
                    'temporal_gap_days' => $article->published_at && $event->occurred_at ? abs($article->published_at->diffInDays($event->occurred_at)) : null,
                ],
                'alternative_explanations' => $alternativeExplanations,
                'unresolved_questions' => $unresolvedQuestions,
                'organization_id' => $tenantId,
            ]);

            $correlations->push($correlated);
        }

        return $correlations;
    }

    /**
     * Determine correlation type.
     */
    protected function determineCorrelationType(Article $article, IntelligenceEvent $event): string
    {
        $sharedEntities = $article->companies->filter(fn($c) => str_contains(strtolower($event->title), strtolower($c->name)));
        if ($sharedEntities->isNotEmpty()) {
            return 'entity_shared';
        }

        if ($article->published_at && $event->occurred_at && abs($article->published_at->diffInDays($event->occurred_at)) <= 14) {
            return 'temporal';
        }

        return 'topic_cascade';
    }

    /**
     * Compute statistical / topical correlation score (0.00 to 1.00).
     */
    protected function calculateCorrelationStrength(Article $article, IntelligenceEvent $event): float
    {
        $score = 0.50;

        // Boost if within 30 days
        if ($article->published_at && $event->occurred_at && abs($article->published_at->diffInDays($event->occurred_at)) <= 30) {
            $score += 0.25;
        }

        // Boost if exact company match
        $matchesEntity = $article->companies->contains(fn($c) => str_contains(strtolower($event->title), strtolower($c->name)));
        if ($matchesEntity) {
            $score += 0.20;
        }

        return min(0.95, round($score, 2));
    }
}
