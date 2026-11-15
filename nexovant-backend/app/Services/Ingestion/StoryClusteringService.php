<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Models\AuditLog;
use App\Models\DiscoveredStory;
use App\Models\StoryCluster;
use App\Models\User;
use Illuminate\Support\Str;

class StoryClusteringService
{
    protected float $clusteringSimilarityThreshold = 0.65;
    protected int $clusterTimeWindowHours = 72;

    /**
     * Alias for clusterStory for compatibility.
     */
    public function assignStoryToCluster(DiscoveredStory $story): StoryCluster
    {
        return $this->clusterStory($story);
    }

    /**
     * Assign a discovered story to an existing active event cluster or initialize a new cluster.
     */
    public function clusterStory(DiscoveredStory $story): StoryCluster
    {
        // If already assigned to an active cluster, return it
        if ($story->cluster_id) {
            $existing = StoryCluster::find($story->cluster_id);
            if ($existing) {
                return $existing;
            }
        }

        $storyPublishedAt = $story->published_at ?: now();
        $windowStart = $storyPublishedAt->copy()->subHours($this->clusterTimeWindowHours);
        $windowEnd = $storyPublishedAt->copy()->addHours($this->clusterTimeWindowHours);

        // Find active candidate clusters
        $candidateClusters = StoryCluster::where('status', '!=', 'archived')
            ->whereBetween('first_seen_at', [$windowStart, $windowEnd])
            ->with('stories:id,title,normalized_title')
            ->get();

        $bestCluster = null;
        $highestSimilarity = 0.0;
        $storyTokens = $this->tokenize($story->normalized_title);

        foreach ($candidateClusters as $cluster) {
            // Compare against cluster title and its member stories
            $clusterTokens = $this->tokenize(mb_strtolower($cluster->title));
            $titleSim = $this->calculateTokenOverlap($storyTokens, $clusterTokens);

            $maxStorySim = 0.0;
            foreach ($cluster->stories as $clusteredStory) {
                $csTokens = $this->tokenize($clusteredStory->normalized_title);
                $sim = $this->calculateTokenOverlap($storyTokens, $csTokens);
                if ($sim > $maxStorySim) {
                    $maxStorySim = $sim;
                }
            }

            $similarity = max($titleSim, $maxStorySim);

            if ($similarity >= $this->clusteringSimilarityThreshold && $similarity > $highestSimilarity) {
                $highestSimilarity = $similarity;
                $bestCluster = $cluster;
            }
        }

        if ($bestCluster) {
            $this->addStoryToCluster($bestCluster, $story, $highestSimilarity);
            return $bestCluster;
        }

        // Create new story cluster
        return $this->createNewCluster($story);
    }

    /**
     * Create a fresh story cluster with the initial story.
     */
    public function createNewCluster(DiscoveredStory $story): StoryCluster
    {
        $publishedAt = $story->published_at ?: now();
        $title = $story->title;
        $slug = Str::slug(substr($title, 0, 80)) . '-' . Str::random(5);

        $cluster = StoryCluster::create([
            'title' => $title,
            'slug' => $slug,
            'summary' => $story->summary,
            'status' => 'discovered',
            'lead_story_id' => $story->id,
            'confidence_score' => 0.85,
            'story_count' => 1,
            'first_seen_at' => $publishedAt,
            'last_seen_at' => $publishedAt,
            'metadata' => [
                'origin_publisher' => $story->source?->name,
                'origin_url' => $story->canonical_url,
            ],
        ]);

        $cluster->stories()->attach($story->id, [
            'similarity_score' => 1.000,
            'is_primary' => true,
        ]);

        $story->update([
            'cluster_id' => $cluster->id,
            'status' => 'clustered',
        ]);

        return $cluster;
    }

    /**
     * Add a story to an existing cluster.
     */
    public function addStoryToCluster(StoryCluster $cluster, DiscoveredStory $story, float $similarity): void
    {
        $publishedAt = $story->published_at ?: now();

        $cluster->stories()->syncWithoutDetaching([
            $story->id => [
                'similarity_score' => round($similarity, 3),
                'is_primary' => false,
            ],
        ]);

        $newCount = $cluster->stories()->count();
        $firstSeen = $cluster->first_seen_at ? min($cluster->first_seen_at, $publishedAt) : $publishedAt;
        $lastSeen = $cluster->last_seen_at ? max($cluster->last_seen_at, $publishedAt) : $publishedAt;

        $cluster->update([
            'story_count' => $newCount,
            'first_seen_at' => $firstSeen,
            'last_seen_at' => $lastSeen,
            'status' => 'clustered',
        ]);

        $story->update([
            'cluster_id' => $cluster->id,
            'status' => 'clustered',
        ]);
    }

    /**
     * Merge two story clusters.
     */
    public function mergeClusters(StoryCluster|string $primary, StoryCluster|string $secondary, ?string $reason = null, ?User $admin = null): StoryCluster
    {
        $primaryCluster = is_string($primary) ? StoryCluster::findOrFail($primary) : $primary;
        $secondaryCluster = is_string($secondary) ? StoryCluster::findOrFail($secondary) : $secondary;

        $secondaryStories = $secondaryCluster->stories()->get();

        foreach ($secondaryStories as $story) {
            $primaryCluster->stories()->syncWithoutDetaching([
                $story->id => [
                    'similarity_score' => $story->pivot->similarity_score ?? 0.85,
                    'is_primary' => false,
                ],
            ]);

            $story->update(['cluster_id' => $primaryCluster->id]);
        }

        $newCount = $primaryCluster->stories()->count();
        $primaryCluster->update([
            'story_count' => $newCount,
            'first_seen_at' => min($primaryCluster->first_seen_at ?? now(), $secondaryCluster->first_seen_at ?? now()),
            'last_seen_at' => max($primaryCluster->last_seen_at ?? now(), $secondaryCluster->last_seen_at ?? now()),
        ]);

        $secondaryCluster->delete();

        if ($admin) {
            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'cluster.merged',
                'resource_type' => 'StoryCluster',
                'resource_id' => $primaryCluster->id,
                'new_values' => [
                    'primary_id' => $primaryCluster->id,
                    'secondary_id' => $secondaryCluster->id,
                    'reason' => $reason,
                ],
                'created_at' => now(),
            ]);
        }

        return $primaryCluster;
    }

    /**
     * Split a cluster by separating designated stories into a new cluster.
     *
     * @param array<int, string> $storyIdsToDetach
     */
    public function splitCluster(StoryCluster|string $cluster, array $storyIdsToDetach, ?string $reason = null, ?User $admin = null): StoryCluster
    {
        $targetCluster = is_string($cluster) ? StoryCluster::findOrFail($cluster) : $cluster;

        $storiesToMove = DiscoveredStory::whereIn('id', $storyIdsToDetach)->get();
        if ($storiesToMove->isEmpty()) {
            return $targetCluster;
        }

        $leadStory = $storiesToMove->first();
        $newCluster = $this->createNewCluster($leadStory);

        foreach ($storiesToMove->skip(1) as $story) {
            $this->addStoryToCluster($newCluster, $story, 0.90);
        }

        // Detach from original cluster
        $targetCluster->stories()->detach($storyIdsToDetach);
        $targetCluster->update([
            'story_count' => $targetCluster->stories()->count(),
        ]);

        if ($admin) {
            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'cluster.split',
                'resource_type' => 'StoryCluster',
                'resource_id' => $targetCluster->id,
                'new_values' => [
                    'original_cluster_id' => $targetCluster->id,
                    'new_cluster_id' => $newCluster->id,
                    'detached_story_ids' => $storyIdsToDetach,
                    'reason' => $reason,
                ],
                'created_at' => now(),
            ]);
        }

        return $newCluster;
    }

    /**
     * Mark a false duplicate as valid and restore to discovery workflow.
     */
    public function markFalseDuplicate(DiscoveredStory $story, ?User $admin = null): void
    {
        $story->update([
            'status' => 'discovered',
            'duplicate_of_id' => null,
        ]);

        // Attempt cluster assignment now that it is recognized as distinct coverage
        $this->clusterStory($story);

        if ($admin) {
            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'story.false_duplicate_resolved',
                'resource_type' => 'DiscoveredStory',
                'resource_id' => $story->id,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Helper to tokenize and filter text.
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
     * Calculate token overlap ratio.
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
        return count($intersection) / min(count($tokensA), count($tokensB));
    }
}
