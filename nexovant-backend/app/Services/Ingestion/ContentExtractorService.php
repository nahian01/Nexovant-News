<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Models\DiscoveredStory;
use App\Models\NewsSource;
use App\Services\Ingestion\DTO\FeedItemDTO;

class ContentExtractorService
{
    /**
     * Common query parameters used for tracking and analytics that should be stripped for canonicalization.
     */
    protected array $trackingParameters = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'ref',
        'ref_src',
        'fbclid',
        'gclid',
        'mc_cid',
        'mc_eid',
        '_ga',
        'campaignid',
        'adgroupid',
        'trk',
        'spm',
        'si',
        'source',
        'feature',
        'ncid',
    ];

    /**
     * Canonicalize a web URL by removing marketing tracking parameters, normalizing host, and cleaning fragments.
     */
    public function canonicalizeUrl(string $url): string
    {
        $parsed = parse_url(trim($url));
        if (!$parsed || empty($parsed['host'])) {
            return trim($url);
        }

        $scheme = strtolower($parsed['scheme'] ?? 'https');
        $host = strtolower($parsed['host']);

        // Remove standard ports
        if (isset($parsed['port'])) {
            if (($scheme === 'http' && $parsed['port'] === 80) || ($scheme === 'https' && $parsed['port'] === 443)) {
                unset($parsed['port']);
            }
        }

        $port = isset($parsed['port']) ? ":{$parsed['port']}" : '';
        $path = $parsed['path'] ?? '/';

        // Normalize trailing slash (remove if path is longer than '/')
        if (strlen($path) > 1 && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // Clean query parameters
        $cleanQuery = '';
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
            foreach ($this->trackingParameters as $badParam) {
                unset($queryParams[$badParam]);
            }
            if (!empty($queryParams)) {
                ksort($queryParams);
                $cleanQuery = '?' . http_build_query($queryParams);
            }
        }

        return "{$scheme}://{$host}{$port}{$path}{$cleanQuery}";
    }

    /**
     * Normalize headline by removing trailing publisher branding, punctuation, and excess whitespace.
     */
    public function normalizeTitle(string $title, ?string $publisherName = null): string
    {
        $normalized = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Strip publisher suffixes e.g. " - TechCrunch", " | Reuters", " — Bloomberg"
        $suffixes = [' - ', ' | ', ' — ', ' – ', ' :: '];
        foreach ($suffixes as $sep) {
            if (str_contains($normalized, $sep)) {
                $parts = explode($sep, $normalized);
                if (count($parts) > 1) {
                    $lastPart = trim(end($parts));
                    // If publisher name matches or ends with known publication string
                    if ($publisherName && stripos($lastPart, $publisherName) !== false) {
                        array_pop($parts);
                        $normalized = implode($sep, $parts);
                    } elseif (strlen($lastPart) < 30) {
                        // General editorial suffix check
                        array_pop($parts);
                        $normalized = implode($sep, $parts);
                    }
                }
            }
        }

        // Lowercase, trim, collapse spaces
        $clean = mb_strtolower(trim($normalized));
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', '', $clean) ?? $clean;
        return preg_replace('/\s+/', ' ', $clean) ?? $clean;
    }

    /**
     * Calculate deterministic content deduplication hash.
     */
    public function calculateContentHash(string $canonicalUrl, string $normalizedTitle): string
    {
        return hash('sha256', trim($canonicalUrl) . '|' . trim($normalizedTitle));
    }

    /**
     * Alias for calculateContentHash for compatibility.
     */
    public function generateContentHash(string $canonicalUrl, string $normalizedTitle): string
    {
        return $this->calculateContentHash($canonicalUrl, $normalizedTitle);
    }

    /**
     * Sanitize and strip unsafe HTML tags from raw article contents.
     */
    public function sanitizeHtml(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Strip dangerous script, iframe, object, style blocks
        $cleaned = preg_replace('/<(script|style|iframe|object|embed)[^>]*?>.*?<\/\\1>/si', '', $html) ?? $html;

        // Strip inline events like onclick, onerror
        $cleaned = preg_replace('/<([a-z][a-z0-9]*)[^>]*?(\bon[a-z]+\s*=\s*([\'\"]).*?\3)[^>]*?>/i', '<$1>', $cleaned) ?? $cleaned;

        // Strip non-semantic tags but allow safe formatting
        $allowedTags = '<p><br><b><strong><i><em><u><a><ul><ol><li><blockquote><h2><h3><h4><h5><h6>';
        return strip_tags($cleaned, $allowedTags);
    }

    /**
     * Alias for sanitizeHtml for compatibility.
     */
    public function sanitizeContent(?string $html): string
    {
        return $this->sanitizeHtml($html);
    }

    /**
     * Transform a parsed FeedItemDTO into a persistent DiscoveredStory.
     */
    public function extractAndStore(FeedItemDTO $item, NewsSource $source): DiscoveredStory
    {
        $canonicalUrl = $this->canonicalizeUrl($item->url);
        $normalizedTitle = $this->normalizeTitle($item->title, $source->publisher_name ?: $source->name);
        $contentHash = $this->calculateContentHash($canonicalUrl, $normalizedTitle);

        $cleanContent = $this->sanitizeHtml($item->contentRaw ?: $item->summary);
        $plainSummary = strip_tags($item->summary ?: substr(strip_tags($cleanContent), 0, 300));

        return DiscoveredStory::updateOrCreate(
            ['content_hash' => $contentHash],
            [
                'source_id' => $source->id,
                'title' => $item->title,
                'normalized_title' => $normalizedTitle,
                'url' => $item->url,
                'canonical_url' => $canonicalUrl,
                'author' => $item->author,
                'published_at' => $item->publishedAt ?: now(),
                'summary' => $plainSummary,
                'content_raw' => $item->contentRaw,
                'content_normalized' => $cleanContent,
                'featured_image' => $item->featuredImage,
                'language' => $item->language ?: $source->language ?: 'en',
                'status' => 'discovered',
                'metadata' => array_merge($item->metadata, [
                    'source_credibility' => $source->credibility_score,
                    'trust_tier' => $source->trust_tier,
                ]),
            ]
        );
    }
}
