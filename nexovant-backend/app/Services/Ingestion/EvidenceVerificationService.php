<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Models\AuditLog;
use App\Models\DiscoveredStory;
use App\Models\ExtractedFact;
use App\Models\StoryCluster;
use App\Models\User;
use App\Services\Intelligence\FactExtractionService;

class EvidenceVerificationService
{
    public function __construct(
        protected FactExtractionService $factExtractionService
    ) {
    }

    /**
     * Alias for extractAndVerifyClusterFacts for compatibility.
     *
     * @return array<int, ExtractedFact>
     */
    public function extractAndVerifyFactsForCluster(StoryCluster $cluster): array
    {
        return $this->extractAndVerifyClusterFacts($cluster);
    }

    /**
     * Extract and cross-verify facts for all stories in a cluster.
     *
     * @return array<int, ExtractedFact>
     */
    public function extractAndVerifyClusterFacts(StoryCluster $cluster): array
    {
        $stories = $cluster->stories()->with('source')->get();
        if ($stories->isEmpty()) {
            return [];
        }

        $allFacts = [];

        // 1. Extract facts from each individual story
        foreach ($stories as $story) {
            $extracted = $this->factExtractionService->extractFacts(
                rawContent: $story->content_normalized ?: $story->summary ?: $story->title,
                sourceName: $story->source?->name
            );

            $claims = $extracted['facts'] ?? [];

            foreach ($claims as $item) {
                $claimText = trim((string) ($item['claim'] ?? ''));
                if (empty($claimText)) {
                    continue;
                }

                $confidence = (float) ($item['confidence'] ?? 0.85);
                $entity = $item['entity'] ?? null;

                // Adjust confidence based on source credibility
                $sourceCredibility = (float) ($story->source?->credibility_score ?? 0.85);
                $weightedConfidence = round(($confidence * 0.7) + ($sourceCredibility * 0.3), 2);

                $fact = ExtractedFact::create([
                    'cluster_id' => $cluster->id,
                    'story_id' => $story->id,
                    'source_id' => $story->source_id,
                    'claim' => $claimText,
                    'entity' => $entity,
                    'confidence_score' => $weightedConfidence,
                    'verification_status' => 'supported_single', // Initial status before comparison
                    'evidence_excerpt' => substr($story->content_normalized ?: $story->summary ?: '', 0, 400),
                    'source_url' => $story->canonical_url,
                    'published_at' => $story->published_at,
                ]);

                $allFacts[] = $fact;
            }
        }

        // 2. Perform Multi-Source Fact Comparison
        $this->compareAndClassifyClaims($cluster);

        return $allFacts;
    }

    /**
     * Cross-reference claims across multiple sources in the cluster to identify consensus and conflicts.
     */
    public function compareAndClassifyClaims(StoryCluster $cluster): void
    {
        $facts = ExtractedFact::where('cluster_id', $cluster->id)->get();
        if ($facts->count() <= 1) {
            return;
        }

        $factGroups = [];

        foreach ($facts as $fact) {
            $matchedGroup = false;
            $factTokens = $this->tokenize($fact->claim);

            foreach ($factGroups as &$group) {
                // Check if claims are semantically discussing the same point
                $firstFact = $group['facts'][0];
                $groupTokens = $this->tokenize($firstFact->claim);
                $overlap = $this->calculateTokenOverlap($factTokens, $groupTokens);

                if ($overlap >= 0.60) {
                    $group['facts'][] = $fact;
                    $group['sources'][$fact->source_id] = true;
                    $matchedGroup = true;
                    break;
                }
            }

            if (!$matchedGroup) {
                $factGroups[] = [
                    'facts' => [$fact],
                    'sources' => [$fact->source_id => true],
                ];
            }
        }

        // Classify each group
        foreach ($factGroups as $group) {
            $distinctSources = count($group['sources']);
            $factsInGroup = $group['facts'];

            if ($distinctSources >= 2) {
                // Check for contradictory claims (e.g. differing numbers or opposing statements)
                $hasConflict = $this->detectContradiction($factsInGroup);

                foreach ($factsInGroup as $f) {
                    $status = $hasConflict ? 'conflicting' : 'supported_multiple';
                    $f->update(['verification_status' => $status]);
                }
            } else {
                foreach ($factsInGroup as $f) {
                    if ($f->confidence_score < 0.60) {
                        $f->update(['verification_status' => 'unverified']);
                    }
                }
            }
        }
    }

    /**
     * Detect numeric or semantic contradictions between claims in a group.
     *
     * @param array<int, ExtractedFact> $facts
     */
    protected function detectContradiction(array $facts): bool
    {
        $numbersFound = [];

        foreach ($facts as $f) {
            preg_match_all('/\b\d+(?:[\.,]\d+)?(?:\s*(?:billion|million|trillion|percent|%|usd|\$))?\b/i', $f->claim, $matches);
            if (!empty($matches[0])) {
                $numbersFound[] = $matches[0];
            }
        }

        // If multiple sources report different numbers on the same topic, mark as conflicting
        if (count($numbersFound) >= 2) {
            $flat = array_map(fn ($arr) => implode(', ', $arr), $numbersFound);
            if (count(array_unique($flat)) > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Manually update the verification status of a fact by an authorized reviewer.
     */
    public function updateFactStatus(
        ExtractedFact $fact,
        string $status,
        ?string $notes = null,
        ?User $reviewer = null
    ): ExtractedFact {
        $fact->update([
            'verification_status' => $status,
            'editorial_notes' => $notes ?: $fact->editorial_notes,
            'verified_by_user_id' => $reviewer?->id,
            'verified_at' => now(),
        ]);

        if ($reviewer) {
            AuditLog::create([
                'user_id' => $reviewer->id,
                'action' => 'fact.verification_updated',
                'resource_type' => 'ExtractedFact',
                'resource_id' => $fact->id,
                'new_values' => [
                    'status' => $status,
                    'notes' => $notes,
                ],
                'created_at' => now(),
            ]);
        }

        return $fact;
    }

    /**
     * Helper to tokenize text.
     *
     * @return array<int, string>
     */
    protected function tokenize(string $text): array
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'in', 'on', 'at', 'to', 'for', 'with', 'by', 'from', 'of', 'is', 'are', 'was', 'were'];
        return array_values(array_filter($words, fn ($w) => strlen($w) > 2 && !in_array($w, $stopWords, true)));
    }

    /**
     * Calculate token overlap.
     *
     * @param array<int, string> $tokensA
     * @param array<int, string> $tokensB
     */
    protected function calculateTokenOverlap(array $tokensA, array $tokensB): float
    {
        if (empty($tokensA) || empty($tokensB)) {
            return 0.0;
        }

        $intersection = array_intersect($tokensA, $tokensB);
        return count($intersection) / max(1, min(count($tokensA), count($tokensB)));
    }
}
