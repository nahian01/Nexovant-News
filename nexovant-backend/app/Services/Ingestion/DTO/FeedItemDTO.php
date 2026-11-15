<?php

declare(strict_types=1);

namespace App\Services\Ingestion\DTO;

use DateTimeInterface;

class FeedItemDTO
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $title,
        public string $url,
        public ?string $summary = null,
        public ?string $contentRaw = null,
        public ?string $author = null,
        public ?DateTimeInterface $publishedAt = null,
        public ?string $featuredImage = null,
        public ?string $language = 'en',
        public ?string $guid = null,
        public array $metadata = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'summary' => $this->summary,
            'author' => $this->author,
            'published_at' => $this->publishedAt?->format(DateTimeInterface::ATOM),
            'featured_image' => $this->featuredImage,
            'language' => $this->language,
            'metadata' => $this->metadata,
        ];
    }
}
