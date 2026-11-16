<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Connectors;

use App\Models\NewsSource;
use App\Services\Ingestion\DTO\FeedItemDTO;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Throwable;

class RestApiSourceConnector implements SourceConnectorInterface
{
    public function supports(NewsSource $source): bool
    {
        return in_array($source->source_type, ['rest_api', 'news_api', 'official_api', 'json_feed'], true);
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
                    'language' => $item->language,
                    'summary' => $item->summary ? substr($item->summary, 0, 150) . '...' : null,
                ];
            }, array_slice($fetchResult['items'], 0, 3));

            return [
                'success' => true,
                'message' => "REST API connected and response normalized. Retrieved " . count($fetchResult['items']) . " records.",
                'latency_ms' => $latencyMs,
                'status' => 'healthy',
                'http_status' => 200,
                'sample_items' => $sampleItems,
                'attribution' => $source->publisher_name ?: $source->name,
            ];
        } catch (Throwable $e) {
            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);
            return [
                'success' => false,
                'message' => "REST API connection failed: {$e->getMessage()}",
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
        $endpoint = $source->api_endpoint ?: $source->feed_url ?: $source->url;
        if (empty($endpoint)) {
            throw new Exception("Source {$source->name} has no API endpoint configured.");
        }

        $this->assertSafeFromSSRF($endpoint);

        $headers = [
            'User-Agent' => 'Nexovant-Intelligence-Connector/2.0 (+https://nexovant.si)',
            'Accept' => 'application/json',
        ];

        // Conditional Request Headers
        if ($source->etag) {
            $headers['If-None-Match'] = $source->etag;
        }
        if ($source->last_modified_header) {
            $headers['If-Modified-Since'] = $source->last_modified_header;
        }

        // Apply Authentication
        $credentials = $source->getDecryptedCredentials();
        if ($credentials) {
            if ($source->auth_type === 'bearer_token' && !empty($credentials['token'])) {
                $headers['Authorization'] = 'Bearer ' . $credentials['token'];
            } elseif ($source->auth_type === 'api_key' && !empty($credentials['key'])) {
                $headerName = $credentials['header_name'] ?? 'X-API-Key';
                $headers[$headerName] = $credentials['key'];
            }
        }

        $timeout = $source->timeout_seconds ?: 15;
        $http = Http::timeout($timeout)->withHeaders($headers);

        if ($source->auth_type === 'basic_auth' && !empty($credentials['username'])) {
            $http = $http->withBasicAuth((string) $credentials['username'], (string) ($credentials['password'] ?? ''));
        }

        $response = $http->get($endpoint);

        if ($response->status() === 304) {
            return [
                'items' => [],
                'etag' => $source->etag,
                'last_modified' => $source->last_modified_header,
                'not_modified' => true,
            ];
        }

        if (!$response->successful()) {
            throw new Exception("REST API returned HTTP status {$response->status()}: " . substr($response->body(), 0, 200));
        }

        $json = $response->json();
        $etag = $response->header('ETag');
        $lastModified = $response->header('Last-Modified');

        $items = $this->normalizeJsonResponse($json, $source, $options['limit'] ?? ($source->max_items_per_fetch ?: 30));

        return [
            'items' => $items,
            'etag' => $etag ?: $source->etag,
            'last_modified' => $lastModified ?: $source->last_modified_header,
            'not_modified' => false,
        ];
    }

    /**
     * Normalize heterogeneous JSON envelopes into canonical FeedItemDTOs.
     *
     * @param mixed $json
     * @return array<int, FeedItemDTO>
     */
    protected function normalizeJsonResponse(mixed $json, NewsSource $source, int $limit = 30): array
    {
        if (!is_array($json)) {
            return [];
        }

        // Auto-detect root envelope
        $rawRecords = [];
        if (isset($json['articles']) && is_array($json['articles'])) {
            $rawRecords = $json['articles'];
        } elseif (isset($json['data']) && is_array($json['data'])) {
            $rawRecords = $json['data'];
        } elseif (isset($json['items']) && is_array($json['items'])) {
            $rawRecords = $json['items'];
        } elseif (isset($json['results']) && is_array($json['results'])) {
            $rawRecords = $json['results'];
        } elseif (array_is_list($json)) {
            $rawRecords = $json;
        }

        $items = [];
        foreach ($rawRecords as $rec) {
            if (count($items) >= $limit) {
                break;
            }
            if (!is_array($rec)) {
                continue;
            }

            $title = (string) ($rec['title'] ?? $rec['headline'] ?? $rec['name'] ?? '');
            $url = (string) ($rec['url'] ?? $rec['link'] ?? $rec['canonical_url'] ?? $source->url);
            if (empty($title) || empty($url)) {
                continue;
            }

            $summary = (string) ($rec['summary'] ?? $rec['description'] ?? $rec['abstract'] ?? $rec['excerpt'] ?? '');
            $content = (string) ($rec['content'] ?? $rec['body'] ?? $rec['text'] ?? $summary);
            $author = (string) ($rec['author'] ?? $rec['byline'] ?? $rec['publisher'] ?? $source->publisher_name ?? '');

            $pubDate = null;
            $rawDate = $rec['published_at'] ?? $rec['pubDate'] ?? $rec['date'] ?? $rec['created_at'] ?? null;
            if ($rawDate) {
                try {
                    $pubDate = Carbon::parse($rawDate);
                } catch (Throwable) {}
            }

            $language = (string) ($rec['language'] ?? $rec['lang'] ?? $source->language ?? 'en');

            $items[] = new FeedItemDTO(
                title: trim($title),
                url: trim($url),
                summary: !empty($summary) ? trim($summary) : null,
                contentRaw: !empty($content) ? trim($content) : null,
                author: !empty($author) ? trim($author) : null,
                publishedAt: $pubDate ?: now(),
                featuredImage: (string) ($rec['image'] ?? $rec['featured_image'] ?? $rec['urlToImage'] ?? null),
                language: $language,
                guid: (string) ($rec['id'] ?? $rec['uuid'] ?? md5($url)),
                metadata: [
                    'source_type' => 'rest_api',
                    'raw_attributes' => array_intersect_key($rec, array_flip(['doi', 'category', 'jurisdiction', 'docket_number', 'source'])),
                ]
            );
        }

        return $items;
    }

    /**
     * SSRF defense check against private and cloud metadata addresses.
     */
    protected function assertSafeFromSSRF(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            throw new Exception("Invalid host in endpoint URL.");
        }

        if (app()->environment('testing') && (str_contains($host, 'example.com') || str_contains($host, 'test.com'))) {
            return;
        }

        $ips = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (empty($ips)) {
            $ip = @gethostbyname($host);
            if ($ip && $ip !== $host) {
                $ips = [['ip' => $ip]];
            }
        }

        foreach ($ips as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (!$ip) continue;

            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new Exception("SSRF Protection: Endpoint IP '{$ip}' belongs to a private or reserved network range.");
            }
            if (str_starts_with($ip, '127.') || str_starts_with($ip, '169.254.') || $ip === '0.0.0.0') {
                throw new Exception("SSRF Protection: Prohibited loopback or link-local IP.");
            }
        }
    }
}
