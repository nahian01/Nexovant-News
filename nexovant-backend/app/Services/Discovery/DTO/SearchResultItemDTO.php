<?php

declare(strict_types=1);

namespace App\Services\Discovery\DTO;

class SearchResultItemDTO
{
    /**
     * @param array<string, mixed>|null $source
     * @param array<int, array<string, mixed>> $matchedEntities
     * @param array<int, string> $matchedTopics
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $excerpt,
        public readonly string $contentType,
        public readonly ?string $publishedAt,
        public readonly ?string $updatedAt,
        public readonly ?array $source,
        public readonly array $matchedEntities,
        public readonly array $matchedTopics,
        public readonly float $relevanceScore,
        public readonly string $relevanceExplanation,
        public readonly string $verificationStatus,
        public readonly int $corroborationCount,
        public readonly string $url,
        public readonly ?string $tenantId = null,
        public readonly string $informationClassification = 'verified_fact' // verified_fact, disputed_claim, inferred_intelligence, unverified_report
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'content_type' => $this->contentType,
            'published_at' => $this->publishedAt,
            'updated_at' => $this->updatedAt,
            'source' => $this->source,
            'matched_entities' => $this->matchedEntities,
            'matched_topics' => $this->matchedTopics,
            'relevance_score' => round($this->relevanceScore, 3),
            'relevance_explanation' => $this->relevanceExplanation,
            'verification_status' => $this->verificationStatus,
            'corroboration_count' => $this->corroborationCount,
            'url' => $this->url,
            'tenant_id' => $this->tenantId,
            'information_classification' => $this->informationClassification,
        ];
    }
}
