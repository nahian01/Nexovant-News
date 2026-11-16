<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Connectors;

use App\Models\NewsSource;
use App\Services\Ingestion\DTO\FeedItemDTO;
use App\Services\Ingestion\FeedCollectorService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use Throwable;

class AcademicRepositoryConnector implements SourceConnectorInterface
{
    public function __construct(
        protected FeedCollectorService $feedCollector,
        protected RestApiSourceConnector $restApiConnector
    ) {}

    public function supports(NewsSource $source): bool
    {
        return in_array($source->source_type, ['academic', 'academic_repository', 'preprint', 'research_archive'], true);
    }

    public function validateEndpoint(string $endpoint): bool
    {
        if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    public function testConnection(NewsSource $source): array
    {
        $startTime = hrtime(true);
        try {
            $fetchResult = $this->fetch($source, ['limit' => 3]);
            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);

            $sampleItems = array_map(function (FeedItemDTO $item) {
                return [
                    'title' => $item->title,
                    'url' => $item->url,
                    'published_at' => $item->publishedAt?->format('Y-m-d H:i:s'),
                    'doi' => $item->metadata['doi'] ?? null,
                    'authors' => $item->metadata['authors'] ?? [],
                    'abstract' => $item->summary ? substr($item->summary, 0, 160) . '...' : null,
                ];
            }, array_slice($fetchResult['items'], 0, 3));

            return [
                'success' => true,
                'message' => "Academic repository verified. Extracted " . count($fetchResult['items']) . " scholarly publications.",
                'latency_ms' => $latencyMs,
                'status' => 'healthy',
                'http_status' => 200,
                'sample_items' => $sampleItems,
                'attribution' => $source->attribution_requirements ?: "Scholarly Publication Archive ({$source->publisher_name})",
            ];
        } catch (Throwable $e) {
            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);
            return [
                'success' => false,
                'message' => "Academic repository connection failed: " . $e->getMessage(),
                'latency_ms' => $latencyMs,
                'status' => 'unavailable',
                'http_status' => 500,
                'sample_items' => [],
                'attribution' => null,
            ];
        }
    }

    public function fetch(NewsSource $source, array $options = []): array
    {
        $endpoint = $source->api_endpoint ?: $source->feed_url;
        if (!$endpoint) {
            throw new Exception("No valid endpoint configured for academic source [ID: {$source->id}].");
        }

        // Check if endpoint is RSS/Atom feed
        if ($source->feed_url && (str_ends_with(strtolower($source->feed_url), '.xml') || str_contains(strtolower($source->feed_url), 'atom') || str_contains(strtolower($source->feed_url), 'feed') || str_contains(strtolower($source->feed_url), 'arxiv'))) {
            try {
                $feedResult = $this->feedCollector->fetchSourceFeed($source);
                $items = array_map(function (FeedItemDTO $dto) use ($source) {
                    $metadata = $dto->metadata ?? [];
                    $metadata['source_type'] = 'academic_repository';
                    $metadata['is_scholarly'] = true;
                    if (!isset($metadata['attribution'])) {
                        $metadata['attribution'] = $source->attribution_requirements ?: "Academic Archive ({$source->publisher_name})";
                    }
                    return new FeedItemDTO(
                        title: $dto->title,
                        url: $dto->url,
                        summary: $dto->summary,
                        contentRaw: $dto->contentRaw,
                        author: $dto->author,
                        publishedAt: $dto->publishedAt,
                        featuredImage: $dto->featuredImage,
                        language: $dto->language,
                        guid: $dto->guid,
                        metadata: $metadata
                    );
                }, $feedResult['items']);

                return [
                    'items' => $items,
                    'etag' => $feedResult['etag'] ?? null,
                    'last_modified' => $feedResult['last_modified'] ?? null,
                    'not_modified' => $feedResult['not_modified'] ?? false,
                ];
            } catch (Throwable $feedErr) {
                Log::warning("Academic XML fetch failed, falling back to REST/JSON if available: " . $feedErr->getMessage());
            }
        }

        // Otherwise use REST connector or custom JSON processing
        $restResult = $this->restApiConnector->fetch($source, $options);
        $items = array_map(function (FeedItemDTO $dto) use ($source) {
            $metadata = $dto->metadata ?? [];
            $metadata['source_type'] = 'academic_repository';
            $metadata['is_scholarly'] = true;
            if (!isset($metadata['attribution'])) {
                $metadata['attribution'] = $source->attribution_requirements ?: "Academic Archive ({$source->publisher_name})";
            }
            return new FeedItemDTO(
                title: $dto->title,
                url: $dto->url,
                summary: $dto->summary,
                contentRaw: $dto->contentRaw,
                author: $dto->author,
                publishedAt: $dto->publishedAt,
                featuredImage: $dto->featuredImage,
                language: $dto->language,
                guid: $dto->guid,
                metadata: $metadata
            );
        }, $restResult['items']);

        return [
            'items' => $items,
            'etag' => $restResult['etag'] ?? null,
            'last_modified' => $restResult['last_modified'] ?? null,
            'not_modified' => $restResult['not_modified'] ?? false,
        ];
    }
}
