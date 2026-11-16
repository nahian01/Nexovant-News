<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\EarlyWarning;
use App\Models\IntelligenceSignal;
use App\Models\SubscriberMonitor;
use App\Models\User;
use App\Services\Notification\DTO\NotificationEventDTO;
use App\Services\Notification\NotificationEngine;
use Illuminate\Support\Facades\Cache;

class IntelligenceAlertService
{
    public function __construct(
        protected NotificationEngine $notificationEngine
    ) {}

    /**
     * Dispatch alert for an Early Warning through Stage 11 NotificationEngine.
     * Enforces deduplication to prevent alert fatigue.
     */
    public function dispatchEarlyWarningAlert(EarlyWarning $warning, array $recipientUserIds = []): int
    {
        $dedupKey = "alert:early_warning:{$warning->id}:{$warning->warning_level}:{$warning->status}";

        // Prevent identical alert dispatch within 1 hour unless escalated
        if (Cache::has($dedupKey)) {
            return 0;
        }

        // If recipients not provided, target subscribers whose monitors match or organization members
        if (empty($recipientUserIds)) {
            $recipientUserIds = $this->resolveRecipientsForWarning($warning);
        }

        if (empty($recipientUserIds)) {
            return 0;
        }

        $priority = match ($warning->warning_level) {
            'critical' => 'urgent',
            'elevated' => 'high',
            'watch' => 'medium',
            default => 'low'
        };

        $eventDto = new NotificationEventDTO(
            eventType: 'early_warning.' . strtolower($warning->warning_level),
            sourceModule: 'intelligence_automation',
            recipientIds: array_map('strval', $recipientUserIds),
            title: "Early Warning [{$warning->warning_level}]: {$warning->title}",
            message: (string) ($warning->explanation['what_changed'] ?? $warning->title),
            link: "/workspace/monitors?warning={$warning->slug}",
            priority: $priority,
            metadata: [
                'warning_id' => $warning->id,
                'signal_id' => $warning->signal_id,
                'warning_level' => $warning->warning_level,
                'independent_sources' => $warning->factor_breakdown['independent_sources'] ?? 1,
            ],
            tenantId: $warning->organization_id,
            idempotencyKey: $dedupKey,
            category: 'early_warning'
        );

        $this->notificationEngine->dispatch($eventDto);
        Cache::put($dedupKey, true, now()->addHour());

        return count($recipientUserIds);
    }

    /**
     * Dispatch alert for a newly detected high-severity intelligence signal.
     */
    public function dispatchSignalAlert(IntelligenceSignal $signal, array $recipientUserIds = []): int
    {
        if (!in_array($signal->severity, ['high', 'critical'], true)) {
            return 0; // Low/informational signals do not trigger immediate push alerts
        }

        $dedupKey = "alert:signal:{$signal->id}:{$signal->severity}";
        if (Cache::has($dedupKey)) {
            return 0;
        }

        if (empty($recipientUserIds)) {
            $recipientUserIds = $this->resolveRecipientsForSignal($signal);
        }

        if (empty($recipientUserIds)) {
            return 0;
        }

        $eventDto = new NotificationEventDTO(
            eventType: 'signal.' . strtolower($signal->severity),
            sourceModule: 'intelligence_automation',
            recipientIds: array_map('strval', $recipientUserIds),
            title: "Signal Detected [{$signal->severity}]: {$signal->title}",
            message: $signal->description ?? $signal->title,
            link: "/workspace/monitors?signal={$signal->slug}",
            priority: $signal->severity === 'critical' ? 'urgent' : 'high',
            metadata: [
                'signal_id' => $signal->id,
                'category' => $signal->signal_category,
                'independent_sources' => $signal->independent_sources_count,
            ],
            tenantId: $signal->organization_id,
            idempotencyKey: $dedupKey,
            category: 'signal_alert'
        );

        $this->notificationEngine->dispatch($eventDto);
        Cache::put($dedupKey, true, now()->addHours(2));

        return count($recipientUserIds);
    }

    /**
     * Identify users whose active SubscriberMonitors match the signal.
     */
    protected function resolveRecipientsForSignal(IntelligenceSignal $signal): array
    {
        $userIds = [];
        $monitors = SubscriberMonitor::where('is_active', true)->get();

        foreach ($monitors as $monitor) {
            if ($this->monitorMatchesSignal($monitor, $signal)) {
                $userIds[] = (string) $monitor->user_id;
            }
        }

        return array_values(array_unique($userIds));
    }

    protected function resolveRecipientsForWarning(EarlyWarning $warning): array
    {
        if ($warning->signal) {
            return $this->resolveRecipientsForSignal($warning->signal);
        }

        return [];
    }

    protected function monitorMatchesSignal(SubscriberMonitor $monitor, IntelligenceSignal $signal): bool
    {
        // 1. Check Keywords
        $signalText = strtolower($signal->title . ' ' . ($signal->description ?? ''));
        $keywords = $monitor->keywords ?? [];

        foreach ($keywords as $kw) {
            if (str_contains($signalText, strtolower((string) $kw))) {
                return true;
            }
        }

        // 2. Check Entities
        $entityNames = $monitor->entity_names ?? [];
        foreach ($entityNames as $en) {
            if (str_contains($signalText, strtolower((string) $en))) {
                return true;
            }
        }

        return false;
    }
}
