<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Connectors;

use App\Models\NewsSource;
use App\Services\Ingestion\DTO\FeedItemDTO;
use App\Services\Ingestion\FeedCollectorService;
use Exception;
use Throwable;

class GovernmentPublicDisclosureConnector implements SourceConnectorInterface
{
    public function __construct(
        protected FeedCollectorService $feedCollector,
        protected RestApiSourceConnector $restApiConnector
    ) {}

    public function supports(NewsSource $source): bool
    {
        return in_array($source->source_type, ['regulatory', 'government', 'official_disclosure', 'public_gazette', 'official_announcement'], true);
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
                    'official_citation' => $item->metadata['legal_citation'] ?? 'Official Disclosure',
                    'summary' => $item->summary ? substr($item->summary, 0, 150) . '...' : null,
                ];
            }, array_slice($fetchResult['items'], 0, 3));

            return [
                'success' => true,
                'message' => "Government / Regulatory disclosure portal verified. Retrieved " . count($fetchResult['items']) . " official notices.",
                'latency_ms' => $latencyMs,
                'status' => 'healthy',
                'http_status' => 200,
                'sample_items' => $sampleItems,
                'attribution' => $source->attribution_requirements ?: "Official Public Notice ({$source->publisher_name})",
            ];
        } catch (Throwable $e) {
            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);
            return [
                'success' => false,
                'message' => "Public disclosure test failed: {$e->getMessage()}",
                'latency_ms' => $latencyMs,
                'status' => 'degraded',
                'http_status' => null,
                'sample_items' => [],
                'attribution' => null,
            ];
        }
    }

    public function fetch(NewsSource $source, array $options = []): array
    {
        $targetUrl = $source->api_endpoint ?: $source->feed_url ?: $source->url;

        // Route between XML (RSS/Atom) and JSON disclosure formats
        if (str_contains(strtolower($targetUrl), '.json') || $source->auth_type !== 'none') {
            $res = $this->restApiConnector->fetch($source, $options);
        } else {
            $res = $this->feedCollector->fetchSourceFeed($source);
        }

        // Enrich with regulatory metadata & primary-source attribution
        $enrichedItems = [];
        foreach ($res['items'] as $item) {
            $meta = $item->metadata;
            $meta['is_primary_source'] = true;
            $meta['regulatory_jurisdiction'] = $source->country_code ?: 'GLOBAL';
            $meta['attribution_notice'] = $source->attribution_requirements ?: "Official public disclosure via {$source->publisher_name}";

            // Extract potential docket / gazette numbers
            if (preg_match('/(?:docket|file|gazette|case|release)\s*no\.?\s*:?\s*([a-z0-9\-\/]+)/i', $item->title . ' ' . ($item->summary ?? ''), $matches)) {
                $meta['legal_citation'] = trim($matches[0]);
                $meta['docket_number'] = trim($matches[1]);
            } else {
                $meta['legal_citation'] = "Official Record ({$source->publisher_name})";
            }

            $enrichedItems[] = new FeedItemDTO(
                title: $item->title,
                url: $item->url,
                summary: $item->summary,
                contentRaw: $item->contentRaw,
                author: $item->author ?: $source->publisher_name,
                publishedAt: $item->publishedAt,
                featuredImage: $item->featuredImage,
                language: $item->language ?: $source->language,
                guid: $item->guid,
                metadata: $meta
            );
        }

        return [
            'items' => $enrichedItems,
            'etag' => $res['etag'] ?? null,
            'last_modified' => $res['last_modified'] ?? null,
            'not_modified' => $res['not_modified'] ?? false,
        ];
    }
}
