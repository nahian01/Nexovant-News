<?php

declare(strict_types=1);

namespace App\Services\SourceNetwork;

use App\Models\NewsSource;
use App\Models\User;
use App\Services\Ingestion\Connectors\SourceConnectorManager;
use App\Services\Ingestion\FeedCollectorService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SourceOnboardingService
{
    public function __construct(
        protected FeedCollectorService $feedCollector,
        protected SourceConnectorManager $connectorManager
    ) {}

    /**
     * Validate an endpoint for URL correctness and SSRF safety.
     *
     * @return array{valid: bool, error: ?string}
     */
    public function validateEndpointSecurity(string $url): array
    {
        try {
            $this->feedCollector->assertUrlSafeFromSSRF($url);
            return ['valid' => true, 'error' => null];
        } catch (Throwable $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Submit a source for onboarding and review.
     *
     * @param array<string, mixed> $data
     * @throws Exception
     */
    public function submitSource(array $data, ?User $user = null): NewsSource
    {
        $primaryUrl = $data['feed_url'] ?? $data['api_endpoint'] ?? $data['website_url'] ?? null;
        if (!$primaryUrl) {
            throw new Exception("Source must provide at least one valid endpoint (feed_url, api_endpoint, or website_url).");
        }

        // SSRF Check
        $securityCheck = $this->validateEndpointSecurity($primaryUrl);
        if (!$securityCheck['valid']) {
            throw new Exception("Security verification failed for endpoint: {$securityCheck['error']}");
        }

        // Canonical domain extraction
        $parsed = parse_url($primaryUrl);
        $canonicalDomain = strtolower($parsed['host'] ?? '');
        if (str_starts_with($canonicalDomain, 'www.')) {
            $canonicalDomain = substr($canonicalDomain, 4);
        }

        return DB::transaction(function () use ($data, $canonicalDomain, $user, $primaryUrl) {
            $slug = Str::slug($data['name']);
            $uniqueSlug = $slug;
            $counter = 1;
            while (NewsSource::where('slug', $uniqueSlug)->exists()) {
                $uniqueSlug = "{$slug}-{$counter}";
                $counter++;
            }

            $source = new NewsSource();
            $source->name = $data['name'];
            $source->slug = $uniqueSlug;
            $source->url = $data['url'] ?? $data['website_url'] ?? $primaryUrl;
            $source->website_url = $data['website_url'] ?? $source->url;
            $source->feed_url = $data['feed_url'] ?? null;
            $source->api_endpoint = $data['api_endpoint'] ?? null;
            $source->source_type = $data['source_type'] ?? 'rss';
            $source->publisher_name = $data['publisher_name'] ?? $data['name'];
            $source->canonical_domain = $canonicalDomain;
            $source->country_code = strtoupper($data['country_code'] ?? 'US');
            $source->continent = $data['continent'] ?? $this->inferContinent($source->country_code);
            $source->language = $data['language'] ?? 'en';
            $source->supported_languages = $data['supported_languages'] ?? [$source->language];
            $source->publication_frequency = $data['publication_frequency'] ?? 'hourly';
            $source->auth_type = $data['auth_type'] ?? 'none';
            $source->attribution_requirements = $data['attribution_requirements'] ?? "Reporting by {$source->publisher_name}";
            $source->terms_of_use_url = $data['terms_of_use_url'] ?? null;
            $source->rate_limit_per_minute = (int) ($data['rate_limit_per_minute'] ?? 60);
            $source->organization_id = $data['organization_id'] ?? null;
            $source->is_active = (bool) ($data['is_active'] ?? false);
            $source->editorial_status = $data['editorial_status'] ?? 'pending_review';
            $source->onboarding_notes = $data['onboarding_notes'] ?? ($user ? "Submitted by user #{$user->id} ({$user->name})" : "Discovered automatically");
            $source->health_status = 'pending';
            $source->reliability_profile = [
                'factual_accuracy_score' => 85,
                'transparency_score' => 80,
                'corroboration_rate' => 75,
                'overall_score' => 80,
                'primary_source_ratio' => in_array($source->source_type, ['regulatory', 'government', 'academic']) ? 0.95 : 0.60,
                'tier' => in_array($source->source_type, ['regulatory', 'government', 'academic']) ? 'Tier 1' : 'Tier 2',
            ];

            // Encrypted credentials if provided
            if (!empty($data['credentials']) && is_array($data['credentials'])) {
                $source->setEncryptedCredentialsAttribute($data['credentials']);
            }

            $source->save();

            // Run initial pre-flight test connection if active or pending_review
            try {
                $testResult = $this->connectorManager->testSource($source);
                $source->update([
                    'health_status' => $testResult['success'] ? 'healthy' : 'degraded',
                    'last_health_check_at' => now(),
                    'last_response_time_ms' => $testResult['latency_ms'] ?? null,
                    'last_http_status' => $testResult['http_status'] ?? null,
                ]);
            } catch (Throwable $e) {
                Log::warning("Initial pre-flight test failed for source {$source->name}: " . $e->getMessage());
            }

            return $source->fresh();
        });
    }

    /**
     * Approve a source and activate it.
     */
    public function approveSource(NewsSource $source, ?string $notes = null, ?User $reviewer = null): NewsSource
    {
        $existingNotes = $source->onboarding_notes ? $source->onboarding_notes . "\n" : '';
        $reviewerTag = $reviewer ? "Reviewer #{$reviewer->id} ({$reviewer->name})" : "Automated Governance";
        $appendNote = "[Approved at " . now()->toIso8601String() . " by {$reviewerTag}] " . ($notes ?? 'Source verified and approved for intelligence ingestion.');

        $source->update([
            'editorial_status' => 'approved',
            'is_active' => true,
            'health_status' => $source->health_status === 'pending' ? 'healthy' : $source->health_status,
            'onboarding_notes' => $existingNotes . $appendNote,
        ]);

        return $source->fresh();
    }

    /**
     * Reject a source.
     */
    public function rejectSource(NewsSource $source, string $reason, ?User $reviewer = null): NewsSource
    {
        $existingNotes = $source->onboarding_notes ? $source->onboarding_notes . "\n" : '';
        $reviewerTag = $reviewer ? "Reviewer #{$reviewer->id} ({$reviewer->name})" : "Automated Governance";
        $appendNote = "[Rejected at " . now()->toIso8601String() . " by {$reviewerTag}] Reason: {$reason}";

        $source->update([
            'editorial_status' => 'rejected',
            'is_active' => false,
            'onboarding_notes' => $existingNotes . $appendNote,
        ]);

        return $source->fresh();
    }

    /**
     * Temporarily suspend a source.
     */
    public function suspendSource(NewsSource $source, string $reason): NewsSource
    {
        $existingNotes = $source->onboarding_notes ? $source->onboarding_notes . "\n" : '';
        $appendNote = "[Suspended at " . now()->toIso8601String() . "] Reason: {$reason}";

        $source->update([
            'editorial_status' => 'temporarily_suspended',
            'is_active' => false,
            'health_status' => 'suspended',
            'onboarding_notes' => $existingNotes . $appendNote,
        ]);

        return $source->fresh();
    }

    /**
     * Archive a source.
     */
    public function archiveSource(NewsSource $source, string $reason): NewsSource
    {
        $existingNotes = $source->onboarding_notes ? $source->onboarding_notes . "\n" : '';
        $appendNote = "[Archived at " . now()->toIso8601String() . "] Reason: {$reason}";

        $source->update([
            'editorial_status' => 'archived',
            'is_active' => false,
            'onboarding_notes' => $existingNotes . $appendNote,
        ]);

        return $source->fresh();
    }

    /**
     * Infer continent from 2-letter ISO country code.
     */
    public function inferContinent(string $countryCode): string
    {
        $map = [
            'BD' => 'Asia', 'IN' => 'Asia', 'PK' => 'Asia', 'CN' => 'Asia', 'JP' => 'Asia', 'SG' => 'Asia', 'AE' => 'Asia', 'SA' => 'Asia', 'KR' => 'Asia', 'MY' => 'Asia', 'TH' => 'Asia', 'ID' => 'Asia', 'VN' => 'Asia',
            'GB' => 'Europe', 'DE' => 'Europe', 'FR' => 'Europe', 'IT' => 'Europe', 'ES' => 'Europe', 'NL' => 'Europe', 'CH' => 'Europe', 'SE' => 'Europe', 'NO' => 'Europe', 'PL' => 'Europe', 'BE' => 'Europe', 'UA' => 'Europe',
            'US' => 'North America', 'CA' => 'North America', 'MX' => 'North America',
            'BR' => 'South America', 'AR' => 'South America', 'CL' => 'South America', 'CO' => 'South America', 'PE' => 'South America',
            'NG' => 'Africa', 'ZA' => 'Africa', 'EG' => 'Africa', 'KE' => 'Africa', 'GH' => 'Africa', 'MA' => 'Africa', 'ET' => 'Africa',
            'AU' => 'Oceania', 'NZ' => 'Oceania',
        ];

        return $map[strtoupper($countryCode)] ?? 'Global';
    }
}
