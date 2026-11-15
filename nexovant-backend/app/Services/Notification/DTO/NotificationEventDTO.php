<?php

declare(strict_types=1);

namespace App\Services\Notification\DTO;

class NotificationEventDTO
{
    /**
     * @param string[] $recipientIds
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $eventType,
        public readonly string $sourceModule,
        public readonly array $recipientIds,
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $link = null,
        public readonly string $priority = 'medium', // low, medium, high, urgent
        public readonly array $metadata = [],
        public readonly ?string $tenantId = null, // organization_id
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $category = null, // breaking, trend_alert, publication, system, billing, editorial, organization
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $recipientIds = isset($data['recipient_ids']) 
            ? (array) $data['recipient_ids'] 
            : (isset($data['user_id']) ? [(string) $data['user_id']] : []);

        return new self(
            eventType: (string) ($data['event_type'] ?? 'system.alert'),
            sourceModule: (string) ($data['source_module'] ?? 'system'),
            recipientIds: $recipientIds,
            title: (string) ($data['title'] ?? 'Notification'),
            message: (string) ($data['message'] ?? ''),
            link: isset($data['link']) ? (string) $data['link'] : null,
            priority: (string) ($data['priority'] ?? 'medium'),
            metadata: isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [],
            tenantId: isset($data['organization_id']) ? (string) $data['organization_id'] : (isset($data['tenant_id']) ? (string) $data['tenant_id'] : null),
            idempotencyKey: isset($data['idempotency_key']) ? (string) $data['idempotency_key'] : null,
            category: isset($data['category']) ? (string) $data['category'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'event_type' => $this->eventType,
            'source_module' => $this->sourceModule,
            'recipient_ids' => $this->recipientIds,
            'title' => $this->title,
            'message' => $this->message,
            'link' => $this->link,
            'priority' => $this->priority,
            'metadata' => $this->metadata,
            'organization_id' => $this->tenantId,
            'idempotency_key' => $this->idempotencyKey,
            'category' => $this->category,
        ];
    }
}
