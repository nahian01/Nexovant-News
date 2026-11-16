<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Connectors;

use App\Models\NewsSource;
use App\Services\Ingestion\DTO\FeedItemDTO;

interface SourceConnectorInterface
{
    /**
     * Determine whether this connector handles the given source.
     */
    public function supports(NewsSource $source): bool;

    /**
     * Validate the endpoint syntax and verify safety against SSRF.
     */
    public function validateEndpoint(string $endpoint): bool;

    /**
     * Test live connection to source with sample item inspection and latency measurement.
     *
     * @return array{success: bool, message: string, latency_ms: int, status: string, http_status: ?int, sample_items: array<int, array<string, mixed>>, attribution: ?string}
     */
    public function testConnection(NewsSource $source): array;

    /**
     * Fetch and parse items from the source using conditional caching and rate limits.
     *
     * @param array<string, mixed> $options
     * @return array{items: array<int, FeedItemDTO>, etag: ?string, last_modified: ?string, not_modified: bool}
     */
    public function fetch(NewsSource $source, array $options = []): array;
}
