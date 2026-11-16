<?php

declare(strict_types=1);

namespace App\Services\Discovery\DTO;

class SearchQueryDTO
{
    /**
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public readonly string $query,
        public readonly string $contentType = 'all',
        public readonly array $filters = [],
        public readonly string $sortBy = 'relevance',
        public readonly int $page = 1,
        public readonly int $perPage = 15,
        public readonly ?string $userId = null,
        public readonly ?string $tenantId = null,
        public readonly bool $isHybrid = true
    ) {}

    /**
     * Create DTO from request input.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, ?string $userId = null, ?string $tenantId = null): self
    {
        return new self(
            query: trim((string) ($data['q'] ?? $data['query'] ?? '')),
            contentType: (string) ($data['content_type'] ?? 'all'),
            filters: (array) ($data['filters'] ?? []),
            sortBy: (string) ($data['sort_by'] ?? 'relevance'),
            page: max(1, (int) ($data['page'] ?? 1)),
            perPage: min(50, max(5, (int) ($data['per_page'] ?? 15))),
            userId: $userId,
            tenantId: $tenantId,
            isHybrid: filter_var($data['hybrid'] ?? true, FILTER_VALIDATE_BOOLEAN)
        );
    }
}
