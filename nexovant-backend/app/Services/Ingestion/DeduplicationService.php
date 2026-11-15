<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Models\DiscoveredStory;
use Carbon\CarbonInterface;

class DeduplicationService
{
    protected float $titleSimilarityThreshold = 0.82;
    protected int $proximityWindowHours = 72;

    /**
     * Check if a URL, title, or content hash duplicates an existing story.
     */
    public function checkDuplicate(string $canonicalUrl, string $title, ?string $contentHash = null): ?DiscoveredStory
    {
        // 1. Match on canonical URL
        $urlMatch = DiscoveredStory::where('canonical_url', $canonicalUrl)->first();
        if ($urlMatch) {
            return $urlMatch;
        }

        // 2. Match on content hash if provided
        if (!empty($contentHash)) {
            $hashMatch = DiscoveredStory::where('content_hash', $contentHash)->first();
            if ($hashMatch) {
                return $hashMatch;
            }
        }

        // 3. Match on fuzzy title
        $normalized = mb_strtolower(trim($title));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        $tempStory = new DiscoveredStory([
            'title' => $title,
            'normalized_title' => $normalized,
            'canonical_url' => $canonicalUrl,
            'content_hash' => $contentHash ?? hash('sha256', "{$canonicalUrl}|{$title}"),
            'published_at' => now(),
        ]);

        $res = $this->detectDuplicate($tempStory);
        return $res['is_duplicate'] ? $res['duplicate_of'] : null;
    }

    /**
     * Check if a discovered story is a duplicate of an existing story in the system.
     *
     * @return array{is_duplicate: bool, duplicate_of: ?DiscoveredStory, match_type: ?string, similarity_score: float}
     */
    public function detectDuplicate(DiscoveredStory $story): array
    {
        // 1. Exact Canonical URL match (different from self)
        $urlMatch = DiscoveredStory::where('canonical_url', $story->canonical_url)
            ->when($story->id, fn ($q) => $q->where('id', '!=', $story->id))
            ->where('status', '!=', 'duplicate')
            ->orderBy('created_at', 'asc')
            ->first();

        if ($urlMatch) {
            return [
                'is_duplicate' => true,
                'duplicate_of' => $urlMatch,
                'match_type' => 'canonical_url',
                'similarity_score' => 1.0,
            ];
        }

        // 2. Exact Normalized Title match within proximity window
        $publishedAt = $story->published_at ?? $story->created_at ?? now();
        $windowStart = $publishedAt->copy()->subHours($this->proximityWindowHours);
        $windowEnd = $publishedAt->copy()->addHours($this->proximityWindowHours);

        $exactTitleMatch = DiscoveredStory::where('normalized_title', $story->normalized_title)
            ->when($story->id, fn ($q) => $q->where('id', '!=', $story->id))
            ->where('status', '!=', 'duplicate')
            ->whereBetween('published_at', [$windowStart, $windowEnd])
            ->orderBy('created_at', 'asc')
            ->first();

        if ($exactTitleMatch) {
            return [
                'is_duplicate' => true,
                'duplicate_of' => $exactTitleMatch,
                'match_type' => 'exact_title',
                'similarity_score' => 1.0,
            ];
        }

        // 3. Fuzzy Title Similarity (Jaccard token similarity & string distance)
        $candidates = DiscoveredStory::when($story->id, fn ($q) => $q->where('id', '!=', $story->id))
            ->where('status', '!=', 'duplicate')
            ->whereBetween('published_at', [$windowStart, $windowEnd])
            ->get(['id', 'title', 'normalized_title', 'published_at', 'created_at']);

        $storyTokens = $this->tokenize($story->normalized_title);

        foreach ($candidates as $candidate) {
            $candTokens = $this->tokenize($candidate->normalized_title);
            $jaccard = $this->calculateJaccardSimilarity($storyTokens, $candTokens);

            // Compute string similarity ratio
            similar_text($story->normalized_title, $candidate->normalized_title, $percent);
            $stringSim = $percent / 100.0;

            // Blended similarity
            $blendedSim = ($jaccard * 0.6) + ($stringSim * 0.4);

            if ($blendedSim >= $this->titleSimilarityThreshold) {
                return [
                    'is_duplicate' => true,
                    'duplicate_of' => $candidate,
                    'match_type' => 'fuzzy_similarity',
                    'similarity_score' => round($blendedSim, 3),
                ];
            }
        }

        return [
            'is_duplicate' => false,
            'duplicate_of' => null,
            'match_type' => null,
            'similarity_score' => 0.0,
        ];
    }

    /**
     * Mark a story as a duplicate and update its database record.
     */
    public function markAsDuplicate(DiscoveredStory $story, DiscoveredStory $primary, string $matchType, float $similarity): void
    {
        $metadata = $story->metadata ?? [];
        $metadata['duplicate_info'] = [
            'primary_id' => $primary->id,
            'match_type' => $matchType,
            'similarity' => $similarity,
            'detected_at' => now()->toIso8601String(),
        ];

        $story->update([
            'status' => 'duplicate',
            'duplicate_of_id' => $primary->id,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Tokenize string into set of significant word tokens.
     *
     * @return array<int, string>
     */
    protected function tokenize(string $text): array
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'in', 'on', 'at', 'to', 'for', 'with', 'by', 'from', 'of', 'is', 'are', 'was', 'were', 'will', 'new', 'as', 'its'];
        return array_values(array_filter($words, fn ($w) => strlen($w) > 2 && !in_array($w, $stopWords, true)));
    }

    /**
     * Calculate Jaccard similarity index between two token sets.
     *
     * @param array<int, string> $tokensA
     * @param array<int, string> $tokensB
     */
    protected function calculateJaccardSimilarity(array $tokensA, array $tokensB): float
    {
        if (empty($tokensA) || empty($tokensB)) {
            return 0.0;
        }

        $intersection = array_intersect($tokensA, $tokensB);
        $union = array_unique(array_merge($tokensA, $tokensB));

        if (empty($union)) {
            return 0.0;
        }

        return count($intersection) / count($union);
    }
}
