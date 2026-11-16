<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Connectors;

use App\Models\NewsSource;
use Exception;
use InvalidArgumentException;

class SourceConnectorManager
{
    /**
     * @var array<int, SourceConnectorInterface>
     */
    protected array $connectors = [];

    public function __construct(
        RssAtomConnector $rssAtomConnector,
        RestApiSourceConnector $restApiConnector,
        GovernmentPublicDisclosureConnector $govConnector,
        AcademicRepositoryConnector $academicConnector
    ) {
        $this->connectors = [
            $govConnector,
            $academicConnector,
            $restApiConnector,
            $rssAtomConnector, // default fallback for feeds
        ];
    }

    public function registerConnector(SourceConnectorInterface $connector, bool $prepend = true): void
    {
        if ($prepend) {
            array_unshift($this->connectors, $connector);
        } else {
            $this->connectors[] = $connector;
        }
    }

    public function getConnector(NewsSource $source): SourceConnectorInterface
    {
        foreach ($this->connectors as $connector) {
            if ($connector->supports($source)) {
                return $connector;
            }
        }

        // If no specific match, default to RssAtomConnector or RestApi based on endpoint
        if (!empty($source->api_endpoint) && empty($source->feed_url)) {
            foreach ($this->connectors as $connector) {
                if ($connector instanceof RestApiSourceConnector) {
                    return $connector;
                }
            }
        }

        foreach ($this->connectors as $connector) {
            if ($connector instanceof RssAtomConnector) {
                return $connector;
            }
        }

        throw new InvalidArgumentException("No suitable connector found for source [ID: {$source->id}, type: {$source->source_type}].");
    }

    /**
     * Execute live test on the source using the matching connector.
     *
     * @return array{success: bool, message: string, latency_ms: int, status: string, http_status: ?int, sample_items: array<int, array<string, mixed>>, attribution: ?string}
     */
    public function testSource(NewsSource $source): array
    {
        $connector = $this->getConnector($source);
        return $connector->testConnection($source);
    }

    /**
     * Fetch from source using matching connector.
     *
     * @param array<string, mixed> $options
     * @return array{items: array<int, \App\Services\Ingestion\DTO\FeedItemDTO>, etag: ?string, last_modified: ?string, not_modified: bool}
     */
    public function fetchSource(NewsSource $source, array $options = []): array
    {
        $connector = $this->getConnector($source);
        return $connector->fetch($source, $options);
    }
}
