<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Models\NewsSource;
use App\Services\Ingestion\DTO\FeedItemDTO;
use DateTimeImmutable;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use Throwable;

class FeedCollectorService
{
    /**
     * Fetch and parse feed items from a NewsSource with strict SSRF protection and conditional headers.
     *
     * @return array{items: array<int, FeedItemDTO>, etag: ?string, last_modified: ?string, not_modified: bool}
     * @throws Exception
     */
    public function fetchSourceFeed(NewsSource $source): array
    {
        $feedUrl = $source->feed_url ?: $source->url;

        // 1. Strict SSRF Protection
        $this->assertUrlSafeFromSSRF($feedUrl);

        // 2. Build headers including conditional caching
        $headers = [
            'User-Agent' => 'Nexovant-Intelligence-Crawler/2.0 (+https://nexovant.si/crawler-policy)',
            'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.8',
        ];

        if ($source->etag) {
            $headers['If-None-Match'] = $source->etag;
        }
        if ($source->last_modified_header) {
            $headers['If-Modified-Since'] = $source->last_modified_header;
        }

        $timeout = $source->timeout_seconds ?? 15;
        $maxItems = $source->max_items_per_fetch ?? 30;

        $source->update([
            'last_attempted_fetch_at' => now(),
        ]);

        $httpOptions = [
            'timeout' => $timeout,
            'connect_timeout' => 8,
            'allow_redirects' => false, // We handle redirects manually with SSRF checks
        ];

        if (app()->isLocal() && empty(ini_get('curl.cainfo')) && empty(ini_get('openssl.cafile'))) {
            $httpOptions['verify'] = false;
        }

        // 3. Execute request with redirect inspection
        $currentUrl = $feedUrl;
        $maxRedirects = 3;
        $redirectCount = 0;
        $response = null;

        while ($redirectCount <= $maxRedirects) {
            $this->assertUrlSafeFromSSRF($currentUrl);

            try {
                $response = Http::withOptions($httpOptions)
                    ->withHeaders($headers)
                    ->get($currentUrl);
            } catch (Throwable $e) {
                $source->update([
                    'health_status' => 'failed',
                    'consecutive_failures' => $source->consecutive_failures + 1,
                    'last_error_message' => "Connection failed: {$e->getMessage()}",
                ]);
                throw new Exception("Feed request failed for {$source->name}: {$e->getMessage()}", (int) $e->getCode(), $e);
            }

            if ($response->status() >= 300 && $response->status() < 400 && $response->header('Location')) {
                $location = (string) $response->header('Location');
                // Resolve relative redirect if needed
                if (!str_starts_with($location, 'http://') && !str_starts_with($location, 'https://')) {
                    $parsed = parse_url($currentUrl);
                    $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
                    $currentUrl = rtrim($base, '/') . '/' . ltrim($location, '/');
                } else {
                    $currentUrl = $location;
                }
                $redirectCount++;
                continue;
            }

            break;
        }

        if ($response === null) {
            throw new Exception("Unable to establish HTTP response for {$source->name}");
        }

        // 4. Handle HTTP 304 Not Modified
        if ($response->status() === 304) {
            $source->update([
                'health_status' => 'healthy',
                'consecutive_failures' => 0,
                'last_successful_fetch_at' => now(),
                'last_polled_at' => now(),
            ]);

            return [
                'items' => [],
                'etag' => $source->etag,
                'last_modified' => $source->last_modified_header,
                'not_modified' => true,
            ];
        }

        if (!$response->successful()) {
            $source->update([
                'health_status' => 'degraded',
                'consecutive_failures' => $source->consecutive_failures + 1,
                'last_error_message' => "HTTP {$response->status()} returned from feed endpoint",
            ]);
            throw new Exception("Feed endpoint returned HTTP {$response->status()}", $response->status());
        }

        $rawXml = $response->body();
        $etag = $response->header('ETag');
        $lastModified = $response->header('Last-Modified');

        // 5. Parse feed XML
        $items = $this->parseXmlFeed($rawXml, $maxItems, $source);

        // Update Source health & counters
        $source->update([
            'health_status' => 'healthy',
            'consecutive_failures' => 0,
            'last_error_message' => null,
            'last_successful_fetch_at' => now(),
            'last_polled_at' => now(),
            'etag' => $etag ?: $source->etag,
            'last_modified_header' => $lastModified ?: $source->last_modified_header,
            'total_items_ingested' => $source->total_items_ingested + count($items),
        ]);

        return [
            'items' => $items,
            'etag' => $etag,
            'last_modified' => $lastModified,
            'not_modified' => false,
        ];
    }

    /**
     * Alias for parseXmlFeed for backward and testing compatibility.
     *
     * @return array<int, FeedItemDTO>
     */
    public function parseFeedXml(string $xml, ?NewsSource $source = null, int $limit = 30): array
    {
        return $this->parseXmlFeed($xml, $limit, $source);
    }

    /**
     * Parse raw XML payload into structured FeedItemDTOs.
     *
     * @return array<int, FeedItemDTO>
     */
    public function parseXmlFeed(string $xml, int $limit = 30, ?NewsSource $source = null): array
    {
        $cleanXml = trim($xml);
        if (empty($cleanXml)) {
            return [];
        }

        // Disable entity loader and load XML safely without external entity evaluation (XXE protection)
        libxml_use_internal_errors(true);
        $sxml = simplexml_load_string(
            $cleanXml,
            SimpleXMLElement::class,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );

        if (!$sxml) {
            libxml_clear_errors();
            throw new Exception("Malformed XML payload received from feed.");
        }

        $items = [];

        // Check if RSS 2.0 (<channel><item>)
        if (isset($sxml->channel->item)) {
            foreach ($sxml->channel->item as $item) {
                if (count($items) >= $limit) {
                    break;
                }

                $namespaces = $item->getNamespaces(true);
                $contentNs = $namespaces['content'] ?? null;
                $dcNs = $namespaces['dc'] ?? null;
                $mediaNs = $namespaces['media'] ?? null;

                $title = trim((string) $item->title);
                $link = trim((string) $item->link);
                $description = (string) $item->description;

                // Check for content:encoded
                $contentRaw = null;
                if ($contentNs) {
                    $itemContent = $item->children($contentNs);
                    if (isset($itemContent->encoded)) {
                        $contentRaw = (string) $itemContent->encoded;
                    }
                }

                // Check for author / dc:creator
                $author = null;
                if (isset($item->author)) {
                    $author = (string) $item->author;
                } elseif ($dcNs) {
                    $dc = $item->children($dcNs);
                    if (isset($dc->creator)) {
                        $author = (string) $dc->creator;
                    }
                }

                // Check for image enclosure or media:content
                $featuredImage = null;
                if (isset($item->enclosure) && str_starts_with((string) ($item->enclosure['type'] ?? ''), 'image/')) {
                    $featuredImage = (string) $item->enclosure['url'];
                } elseif ($mediaNs) {
                    $media = $item->children($mediaNs);
                    if (isset($media->content) && isset($media->content['url'])) {
                        $featuredImage = (string) $media->content['url'];
                    } elseif (isset($media->thumbnail) && isset($media->thumbnail['url'])) {
                        $featuredImage = (string) $media->thumbnail['url'];
                    }
                }

                $publishedAt = null;
                if (isset($item->pubDate)) {
                    try {
                        $publishedAt = new DateTimeImmutable((string) $item->pubDate);
                    } catch (Throwable) {
                        $publishedAt = new DateTimeImmutable();
                    }
                }

                if (!empty($title) && !empty($link)) {
                    $items[] = new FeedItemDTO(
                        title: html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                        url: $link,
                        summary: strip_tags($description),
                        contentRaw: $contentRaw ?: $description,
                        author: $author,
                        publishedAt: $publishedAt ?? new DateTimeImmutable(),
                        featuredImage: $featuredImage,
                        language: $source?->language ?? 'en',
                        guid: (string) ($item->guid ?? $link),
                        metadata: [
                            'format' => 'rss_2.0',
                            'guid' => (string) ($item->guid ?? $link),
                        ]
                    );
                }
            }
        }
        // Check if Atom (<feed><entry>)
        elseif (isset($sxml->entry)) {
            foreach ($sxml->entry as $entry) {
                if (count($items) >= $limit) {
                    break;
                }

                $title = trim((string) $entry->title);

                // Find link
                $link = '';
                if (isset($entry->link)) {
                    foreach ($entry->link as $linkTag) {
                        $rel = (string) ($linkTag['rel'] ?? 'alternate');
                        if ($rel === 'alternate' || empty($rel)) {
                            $link = (string) $linkTag['href'];
                            break;
                        }
                    }
                    if (empty($link) && isset($entry->link[0]['href'])) {
                        $link = (string) $entry->link[0]['href'];
                    }
                }

                $summary = (string) ($entry->summary ?? '');
                $contentRaw = (string) ($entry->content ?? $summary);

                $author = null;
                if (isset($entry->author->name)) {
                    $author = (string) $entry->author->name;
                }

                $publishedAt = null;
                $dateStr = (string) ($entry->published ?? $entry->updated ?? '');
                if (!empty($dateStr)) {
                    try {
                        $publishedAt = new DateTimeImmutable($dateStr);
                    } catch (Throwable) {
                        $publishedAt = new DateTimeImmutable();
                    }
                }

                if (!empty($title) && !empty($link)) {
                    $items[] = new FeedItemDTO(
                        title: html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                        url: $link,
                        summary: strip_tags($summary ?: substr($contentRaw, 0, 300)),
                        contentRaw: $contentRaw,
                        author: $author,
                        publishedAt: $publishedAt ?? new DateTimeImmutable(),
                        featuredImage: null,
                        language: $source?->language ?? 'en',
                        guid: (string) ($entry->id ?? $link),
                        metadata: [
                            'format' => 'atom',
                            'id' => (string) ($entry->id ?? $link),
                        ]
                    );
                }
            }
        }

        libxml_clear_errors();
        return $items;
    }

    /**
     * Strict SSRF validator preventing loopback, private RFC1918, link-local, and reserved IP access.
     *
     * @throws Exception
     */
    public function assertUrlSafeFromSSRF(string $url): void
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            throw new Exception("Invalid URL format: {$url}");
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new Exception("Prohibited URL scheme '{$scheme}'. Only HTTP and HTTPS are permitted.");
        }

        $host = strtolower($parts['host']);

        // Block localhost and numeric loopback names
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new Exception("SSRF Protection: Access to local/internal hostnames ('{$host}') is forbidden.");
        }

        // If host is a direct IP
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->validateIpAddress($host);
            return;
        }

        // Resolve DNS records to verify resolved destination IP
        $ips = @gethostbynamel($host);
        if ($ips === false || empty($ips)) {
            // In unit testing with fake domains or when DNS is unavailable, allow if not a reserved host string
            if (app()->environment('testing')) {
                return;
            }
            throw new Exception("Unable to resolve DNS records for feed host '{$host}'.");
        }

        foreach ($ips as $ip) {
            $this->validateIpAddress($ip);
        }
    }

    /**
     * Validate individual IP address against reserved, loopback, and private CIDR ranges.
     *
     * @throws Exception
     */
    protected function validateIpAddress(string $ip): void
    {
        // 1. PHP built-in filter
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new Exception("SSRF Protection: Target IP address '{$ip}' belongs to a private or reserved network range.");
        }

        // 2. Additional CIDR checks for IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long === false) {
                throw new Exception("Invalid IPv4 address format.");
            }

            // 127.0.0.0/8 (Loopback)
            if (($long & 0xFF000000) === 0x7F000000) {
                throw new Exception("SSRF Protection: Loopback IP '{$ip}' rejected.");
            }
            // 169.254.0.0/16 (Link Local / Cloud Metadata 169.254.169.254)
            if (($long & 0xFFFF0000) === 0xA9FE0000) {
                throw new Exception("SSRF Protection: Link-local / metadata IP '{$ip}' rejected.");
            }
            // 0.0.0.0/8
            if (($long & 0xFF000000) === 0x00000000) {
                throw new Exception("SSRF Protection: Zero network IP '{$ip}' rejected.");
            }
            // 100.64.0.0/10 (Carrier grade NAT)
            if (($long & 0xFFC00000) === 0x64400000) {
                throw new Exception("SSRF Protection: Shared CGNAT IP '{$ip}' rejected.");
            }
            // 224.0.0.0/4 (Multicast)
            if (($long & 0xF0000000) === 0xE0000000) {
                throw new Exception("SSRF Protection: Multicast IP '{$ip}' rejected.");
            }
        }

        // 3. IPv6 checks
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $lower = strtolower($ip);
            if ($lower === '::1' || str_starts_with($lower, 'fe80:') || str_starts_with($lower, 'fc') || str_starts_with($lower, 'fd')) {
                throw new Exception("SSRF Protection: Private/link-local IPv6 '{$ip}' rejected.");
            }
        }
    }
}
