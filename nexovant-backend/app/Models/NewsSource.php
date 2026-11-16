<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

class NewsSource extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'news_sources';

    protected $fillable = [
        'name',
        'publisher_name',
        'description',
        'slug',
        'canonical_domain',
        'source_type',
        'source_category',
        'geographic_region',
        'country_code',
        'continent',
        'language',
        'supported_languages',
        'url',
        'website_url',
        'feed_url',
        'api_endpoint',
        'auth_type',
        'encrypted_credentials',
        'credibility_score',
        'trust_tier',
        'reliability_profile',
        'is_active',
        'editorial_status',
        'onboarding_notes',
        'attribution_requirements',
        'terms_of_use_url',
        'rate_limit_per_minute',
        'max_items_per_fetch',
        'timeout_seconds',
        'publication_frequency',
        'etag',
        'last_modified_header',
        'source_config',
        'last_polled_at',
        'last_successful_fetch_at',
        'last_attempted_fetch_at',
        'last_health_check_at',
        'health_status',
        'last_response_time_ms',
        'last_http_status',
        'last_error_message',
        'fetch_interval_minutes',
        'consecutive_failures',
        'total_items_ingested',
        'organization_id',
    ];

    protected $hidden = [
        'encrypted_credentials',
    ];

    protected function casts(): array
    {
        return [
            'credibility_score' => 'decimal:2',
            'is_active' => 'boolean',
            'rate_limit_per_minute' => 'integer',
            'max_items_per_fetch' => 'integer',
            'timeout_seconds' => 'integer',
            'fetch_interval_minutes' => 'integer',
            'consecutive_failures' => 'integer',
            'total_items_ingested' => 'integer',
            'last_response_time_ms' => 'integer',
            'last_http_status' => 'integer',
            'supported_languages' => 'array',
            'reliability_profile' => 'array',
            'source_config' => 'array',
            'last_polled_at' => 'datetime',
            'last_successful_fetch_at' => 'datetime',
            'last_attempted_fetch_at' => 'datetime',
            'last_health_check_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NewsSource $source): void {
            if (empty($source->url)) {
                $source->url = $source->website_url ?: ($source->feed_url ?: ($source->api_endpoint ?: 'https://news.nexovant.test'));
            }

            if (empty($source->website_url) && !empty($source->url)) {
                $source->website_url = $source->url;
            }

            if (empty($source->slug) && !empty($source->name)) {
                $source->slug = Str::slug($source->name) . '-' . Str::random(4);
            }

            if (empty($source->canonical_domain) && !empty($source->url)) {
                $source->canonical_domain = parse_url($source->url, PHP_URL_HOST) ?: null;
            }

            if (empty($source->source_type)) {
                $source->source_type = 'rss';
            }
        });
    }

    /**
     * Safely store encrypted credentials. Never store plaintext credentials.
     */
    public function setEncryptedCredentialsAttribute(mixed $value): void
    {
        if (empty($value)) {
            $this->attributes['encrypted_credentials'] = null;
            return;
        }

        $payload = is_array($value) ? json_encode($value) : (string) $value;
        $this->attributes['encrypted_credentials'] = Crypt::encryptString($payload);
    }

    /**
     * Retrieve decrypted credentials for authorized connector execution.
     *
     * @return array<string, mixed>|null
     */
    public function getDecryptedCredentials(): ?array
    {
        $raw = $this->attributes['encrypted_credentials'] ?? null;
        if (empty($raw)) {
            return null;
        }

        try {
            $decrypted = Crypt::decryptString($raw);
            $decoded = json_decode($decrypted, true);
            return is_array($decoded) ? $decoded : ['token' => $decrypted];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Check whether credentials exist while keeping secrets masked.
     */
    public function hasCredentials(): bool
    {
        return !empty($this->attributes['encrypted_credentials']);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'source_id');
    }

    public function discoveredStories(): HasMany
    {
        return $this->hasMany(DiscoveredStory::class, 'source_id');
    }

    public function facts(): HasMany
    {
        return $this->hasMany(ExtractedFact::class, 'source_id');
    }

    public function pipelineJobs(): HasMany
    {
        return $this->hasMany(PipelineJob::class, 'source_id');
    }

    public function healthLogs(): HasMany
    {
        return $this->hasMany(SourceHealthLog::class, 'source_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
