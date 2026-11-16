<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Connectors;

use App\Models\NewsSource;
use App\Services\Ingestion\DTO\FeedItemDTO;
use App\Services\Ingestion\FeedCollectorService;
use Exception;
use Illuminate\Support\Facades\Http;
use Throwable;

class RssAtomConnector implements SourceConnectorInterface
{
    public function __construct(
        protected FeedCollectorService $feedCollector
    ) {}

    public function supports(NewsSource $source): bool
    {
        return in_array($source->source_type, ['rss', 'atom', 'wire', 'news_feed', 'press_release'], true)
            || empty($source->source_type);
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
        $targetUrl = $source->feed_url ?: $source->url;
        $startTime = hrtime(true);

        try {
            $fetchResult = $this->feedCollector->fetchSourceFeed($source);
            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);

            $sampleItems = array_map(function (FeedItemDTO $item) {
                return [
                    'title' => $item->title,
                    'url' => $item->url,
                    'published_at' => $item->publishedAt?->format('Y-m-d H:i:s'),
                    'language' => $item->language,
                    'summary' => $item->summary ? substr($item->summary, 0, 150) . '...' : null,
                ];
            }, array_slice($fetchResult['items'], 0, 3));

            return [
                'success' => true,
                'message' => "RSS/Atom feed parsed successfully. Retrieved " . count($fetchResult['items']) . " items.",
                'latency_ms' => $latencyMs,
                'status' => 'healthy',
                'http_status' => 200,
                'sample_items' => $sampleItems,
                'attribution' => $source->publisher_name ?: $source->name,
            ];
        } catch (Throwable $e) {
            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);
            $httpCode = (int) $e->getCode();
            if ($httpCode === 0 && preg_match('/HTTP\s+(\d{3})/', $e->getMessage(), $m)) {
                $httpCode = (int) $m[1];
            }
            $status = $httpCode >= 500 ? 'unavailable' : ($httpCode === 401 || $httpCode === 403 ? 'auth_required' : ($httpCode === 429 ? 'rate_limited' : 'degraded'));
            return [
                'success' => false,
                'message' => "RSS/Atom connection test failed: {$e->getMessage()}",
                'latency_ms' => $latencyMs,
                'status' => $status,
                'http_status' => $httpCode ?: null,
                'sample_items' => [],
                'attribution' => null,
            ];
        }
    }

    public function fetch(NewsSource $source, array $options = []): array
    {
        return $this->feedCollector->fetchSourceFeed($source);
    }
}
