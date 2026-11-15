<?php

declare(strict_types=1);

namespace App\Services\Research;

use App\Models\EntityAlias;
use App\Models\EntityHistory;
use App\Models\EntityRelationship;
use App\Models\EntityResolutionReview;
use App\Models\IntelligenceEntity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EntityResolutionService
{
    /**
     * Resolve a candidate entity name against existing entities, aliases, and fuzzy variations.
     *
     * @param array<string, mixed> $attributes
     */
    public function resolveEntity(string $name, string $type = 'company', array $attributes = []): IntelligenceEntity
    {
        $normalizedName = trim($name);
        if ($normalizedName === '') {
            $normalizedName = 'Unknown Entity';
        }

        // 1. Direct canonical name match
        $canonical = IntelligenceEntity::where('entity_type', $type)
            ->where('verification_status', '!=', 'merged')
            ->where(function ($q) use ($normalizedName) {
                $q->where('canonical_name', $normalizedName)
                  ->orWhereRaw('LOWER(canonical_name) = ?', [strtolower($normalizedName)]);
            })
            ->first();

        if ($canonical) {
            $canonical->update(['last_seen_at' => now()]);
            return $canonical;
        }

        // 2. Direct alias match
        $aliasMatch = EntityAlias::where('alias', $normalizedName)
            ->orWhereRaw('LOWER(alias) = ?', [strtolower($normalizedName)])
            ->with('entity')
            ->first();

        if ($aliasMatch && $aliasMatch->entity && $aliasMatch->entity->verification_status !== 'merged') {
            $aliasMatch->entity->update(['last_seen_at' => now()]);
            return $aliasMatch->entity;
        }

        // 3. Fuzzy match against existing entities of the same type
        $candidates = IntelligenceEntity::where('entity_type', $type)
            ->where('verification_status', '!=', 'merged')
            ->get();

        $bestMatch = null;
        $highestSimilarity = 0.0;

        foreach ($candidates as $cand) {
            $sim = $this->calculateSimilarity($normalizedName, $cand->canonical_name);
            if ($sim > $highestSimilarity) {
                $highestSimilarity = $sim;
                $bestMatch = $cand;
            }
        }

        // High confidence threshold (>= 90%) -> associate and record alias
        if ($highestSimilarity >= 0.90 && $bestMatch !== null) {
            $bestMatch->addAlias($normalizedName, 'alternative_spelling', 'fuzzy_resolution', $highestSimilarity);
            $bestMatch->update(['last_seen_at' => now()]);
            return $bestMatch;
        }

        // Low-to-moderate confidence (70% - 89%) -> Create new entity but queue for review!
        $newEntity = $this->createEntity($normalizedName, $type, $attributes);

        if ($highestSimilarity >= 0.70 && $bestMatch !== null) {
            EntityResolutionReview::create([
                'entity_id' => $newEntity->id,
                'candidate_name' => $normalizedName,
                'candidate_type' => $type,
                'potential_match_entity_id' => $bestMatch->id,
                'confidence_score' => $highestSimilarity,
                'status' => 'pending',
                'match_reason' => sprintf('Fuzzy similarity %.2f%% with "%s"', $highestSimilarity * 100, $bestMatch->canonical_name),
            ]);
        }

        return $newEntity;
    }

    /**
     * Create a brand-new intelligence entity.
     *
     * @param array<string, mixed> $attributes
     */
    public function createEntity(string $name, string $type = 'company', array $attributes = []): IntelligenceEntity
    {
        $normalizedName = trim($name);
        $slug = Str::slug($normalizedName);
        if (IntelligenceEntity::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::lower(Str::random(5));
        }

        $entity = IntelligenceEntity::create([
            'entity_type' => $type,
            'canonical_name' => $normalizedName,
            'slug' => $slug,
            'legal_name' => $attributes['legal_name'] ?? null,
            'description' => $attributes['description'] ?? null,
            'website_url' => $attributes['website_url'] ?? null,
            'country' => $attributes['country'] ?? null,
            'headquarters' => $attributes['headquarters'] ?? null,
            'industry' => $attributes['industry'] ?? null,
            'founding_year' => $attributes['founding_year'] ?? null,
            'confidence_score' => $attributes['confidence_score'] ?? 1.00,
            'verification_status' => $attributes['verification_status'] ?? 'unverified',
            'metadata' => $attributes['metadata'] ?? [],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        EntityHistory::create([
            'entity_id' => $entity->id,
            'action' => 'created',
            'changes' => $entity->toArray(),
            'notes' => 'Entity created via resolution engine',
            'user_id' => $attributes['user_id'] ?? null,
        ]);

        return $entity;
    }

    /**
     * Merge a source entity into a target entity with audit trail and relationship migration.
     */
    public function mergeEntities(
        IntelligenceEntity $target,
        IntelligenceEntity $source,
        string $reason,
        ?string $userId = null
    ): IntelligenceEntity {
        return DB::transaction(function () use ($target, $source, $reason, $userId) {
            // Re-point aliases
            EntityAlias::where('entity_id', $source->id)->update(['entity_id' => $target->id]);

            // Add source canonical name as an alias of target
            $target->addAlias($source->canonical_name, 'former_name', 'entity_merge');

            // Migrate outgoing relationships
            EntityRelationship::where('source_entity_id', $source->id)
                ->where('target_entity_id', '!=', $target->id)
                ->update(['source_entity_id' => $target->id]);

            // Migrate incoming relationships
            EntityRelationship::where('target_entity_id', $source->id)
                ->where('source_entity_id', '!=', $target->id)
                ->update(['target_entity_id' => $target->id]);

            // Mark source as merged
            $source->update([
                'verification_status' => 'merged',
                'merged_into_id' => $target->id,
            ]);

            // Update pending review queue items
            EntityResolutionReview::where('entity_id', $source->id)
                ->update(['status' => 'merged', 'reviewed_by_user_id' => $userId, 'reviewed_at' => now()]);

            // Audit records
            EntityHistory::create([
                'entity_id' => $target->id,
                'action' => 'merged',
                'changes' => ['merged_source_id' => $source->id, 'source_name' => $source->canonical_name],
                'notes' => $reason,
                'user_id' => $userId,
            ]);

            EntityHistory::create([
                'entity_id' => $source->id,
                'action' => 'merged',
                'changes' => ['merged_into_id' => $target->id, 'target_name' => $target->canonical_name],
                'notes' => $reason,
                'user_id' => $userId,
            ]);

            return $target->fresh();
        });
    }

    /**
     * Split selected aliases from an existing entity into a new distinct entity.
     *
     * @param array<int, string> $aliasIds
     */
    public function splitEntity(
        IntelligenceEntity $entity,
        array $aliasIds,
        string $newCanonicalName,
        string $reason,
        ?string $userId = null
    ): IntelligenceEntity {
        return DB::transaction(function () use ($entity, $aliasIds, $newCanonicalName, $reason, $userId) {
            $newEntity = $this->createEntity($newCanonicalName, $entity->entity_type, [
                'user_id' => $userId,
                'verification_status' => 'verified',
            ]);

            // Re-point selected aliases to the new entity
            EntityAlias::where('entity_id', $entity->id)
                ->whereIn('id', $aliasIds)
                ->update(['entity_id' => $newEntity->id]);

            // Audit histories
            EntityHistory::create([
                'entity_id' => $entity->id,
                'action' => 'split',
                'changes' => ['split_new_entity_id' => $newEntity->id, 'new_name' => $newCanonicalName, 'alias_ids' => $aliasIds],
                'notes' => $reason,
                'user_id' => $userId,
            ]);

            EntityHistory::create([
                'entity_id' => $newEntity->id,
                'action' => 'split',
                'changes' => ['split_from_entity_id' => $entity->id, 'original_name' => $entity->canonical_name],
                'notes' => $reason,
                'user_id' => $userId,
            ]);

            return $newEntity;
        });
    }

    /**
     * Verify an entity as editor-confirmed.
     */
    public function verifyEntity(IntelligenceEntity $entity, ?string $userId = null): IntelligenceEntity
    {
        $entity->update(['verification_status' => 'verified']);

        EntityHistory::create([
            'entity_id' => $entity->id,
            'action' => 'verified',
            'changes' => ['verification_status' => 'verified'],
            'notes' => 'Entity verified by editorial staff',
            'user_id' => $userId,
        ]);

        return $entity;
    }

    /**
     * Mark an entity as disputed.
     */
    public function disputeEntity(IntelligenceEntity $entity, string $reason, ?string $userId = null): IntelligenceEntity
    {
        $entity->update(['verification_status' => 'disputed']);

        EntityHistory::create([
            'entity_id' => $entity->id,
            'action' => 'disputed',
            'changes' => ['verification_status' => 'disputed'],
            'notes' => $reason,
            'user_id' => $userId,
        ]);

        return $entity;
    }

    /**
     * Archive an entity.
     */
    public function archiveEntity(IntelligenceEntity $entity, ?string $userId = null): IntelligenceEntity
    {
        $entity->update(['verification_status' => 'archived']);

        EntityHistory::create([
            'entity_id' => $entity->id,
            'action' => 'archived',
            'changes' => ['verification_status' => 'archived'],
            'notes' => 'Entity archived',
            'user_id' => $userId,
        ]);

        return $entity;
    }

    /**
     * Restore an archived entity.
     */
    public function restoreEntity(IntelligenceEntity $entity, ?string $userId = null): IntelligenceEntity
    {
        $entity->update(['verification_status' => 'unverified']);

        EntityHistory::create([
            'entity_id' => $entity->id,
            'action' => 'restored',
            'changes' => ['verification_status' => 'unverified'],
            'notes' => 'Entity restored from archive',
            'user_id' => $userId,
        ]);

        return $entity;
    }

    /**
     * Calculate string similarity between two entity names.
     */
    protected function calculateSimilarity(string $s1, string $s2): float
    {
        $str1 = strtolower(trim($s1));
        $str2 = strtolower(trim($s2));

        if ($str1 === $str2) {
            return 1.0;
        }

        // Clean corporate legal suffixes for root comparison
        $suffixes = ['/\b(inc|incorporated|corp|corporation|llc|ltd|limited|co|company|technologies|technology|group)\b/i', '/[^a-z0-9 ]/'];
        $root1 = trim(preg_replace($suffixes, ' ', $str1));
        $root2 = trim(preg_replace($suffixes, ' ', $str2));
        $root1 = preg_replace('/\s+/', ' ', $root1);
        $root2 = preg_replace('/\s+/', ' ', $root2);

        if ($root1 !== '' && $root1 === $root2) {
            return 0.95;
        }

        // Levenshtein ratio on raw and root
        $len = max(strlen($str1), strlen($str2));
        $lev = ($len > 0) ? 1.0 - (levenshtein($str1, $str2) / $len) : 1.0;

        $rootLen = max(strlen($root1), strlen($root2));
        $rootLev = ($rootLen > 0) ? 1.0 - (levenshtein($root1, $root2) / $rootLen) : 1.0;

        // Word token overlap (Jaccard on words)
        $words1 = array_unique(array_filter(explode(' ', preg_replace('/[^a-z0-9 ]/', '', $str1))));
        $words2 = array_unique(array_filter(explode(' ', preg_replace('/[^a-z0-9 ]/', '', $str2))));

        $intersection = count(array_intersect($words1, $words2));
        $union = count(array_unique(array_merge($words1, $words2)));
        $jaccardSim = $union > 0 ? ($intersection / $union) : 0.0;

        return max($lev, $rootLev, $jaccardSim);
    }
}
