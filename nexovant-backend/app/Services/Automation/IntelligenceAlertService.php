<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\EarlyWarning;
use App\Models\IntelligenceSignal;
use App\Models\OrganizationMember;
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
     * Dispatch alert when an early warning is escalated to a higher threat level.
     */
    public function dispatchWarningEscalatedAlert(
        EarlyWarning $warning,
        string $previousLevel = 'watch',
        array $recipientUserIds = []
    ): int {
        $dedupKey = "alert:warning_escalated:{$warning->id}:{$previousLevel}_to_{$warning->warning_level}";

        if (Cache::has($dedupKey)) {
            return 0;
        }

        if (empty($recipientUserIds)) {
            $recipientUserIds = $this->resolveRecipientsForWarning($warning);
        }

        if (empty($recipientUserIds)) {
            return 0;
        }

        $priority = $warning->warning_level === 'critical' ? 'urgent' : 'high';

        $eventDto = new NotificationEventDTO(
            eventType: 'early_warning.escalated',
            sourceModule: 'intelligence_automation',
            recipientIds: array_map('strval', $recipientUserIds),
            title: "Early Warning Escalated: [{$warning->warning_level}] {$warning->title}",
            message: "Warning level upgraded from {$previousLevel} to {$warning->warning_level}. Corroborated intelligence demands heightened monitoring.",
            link: "/workspace/monitors?warning={$warning->slug}",
            priority: $priority,
            metadata: [
                'warning_id' => $warning->id,
                'previous_level' => $previousLevel,
                'current_level' => $warning->warning_level,
                'status' => $warning->status,
            ],
            tenantId: $warning->organization_id,
            idempotencyKey: $dedupKey,
            category: 'early_warning_escalation'
        );

        $this->notificationEngine->dispatch($eventDto);
        Cache::put($dedupKey, true, now()->addHours(2));

        return count($recipientUserIds);
    }

    /**
     * Dispatch alert when material or contradictory evidence is attached to a warning.
     */
    public function dispatchWarningEvidenceUpdateAlert(
        EarlyWarning $warning,
        string $evidenceType = 'supporting',
        string $summary = '',
        array $recipientUserIds = []
    ): int {
        $isContradictory = in_array(strtolower($evidenceType), ['contradictory', 'disputed', 'conflicting'], true);
        $dedupKey = "alert:warning_evidence:{$warning->id}:{$evidenceType}:" . md5($summary);

        if (Cache::has($dedupKey)) {
            return 0;
        }

        if (empty($recipientUserIds)) {
            $recipientUserIds = $this->resolveRecipientsForWarning($warning);
        }

        if (empty($recipientUserIds)) {
            return 0;
        }

        $title = $isContradictory
            ? "Contradictory Evidence Logged: {$warning->title}"
            : "Material Evidence Update: {$warning->title}";

        $message = !empty($summary)
            ? $summary
            : ($isContradictory
                ? "Contradictory evidence has been reported challenging preliminary assessments for this warning."
                : "New independent corroborate reporting has been verified for this warning.");

        $eventDto = new NotificationEventDTO(
            eventType: $isContradictory ? 'early_warning.evidence_disputed' : 'early_warning.evidence_corroborated',
            sourceModule: 'intelligence_automation',
            recipientIds: array_map('strval', $recipientUserIds),
            title: $title,
            message: $message,
            link: "/workspace/monitors?warning={$warning->slug}",
            priority: $isContradictory ? 'urgent' : 'high',
            metadata: [
                'warning_id' => $warning->id,
                'evidence_type' => $evidenceType,
                'is_contradictory' => $isContradictory,
            ],
            tenantId: $warning->organization_id,
            idempotencyKey: $dedupKey,
            category: 'early_warning_evidence'
        );

        $this->notificationEngine->dispatch($eventDto);
        Cache::put($dedupKey, true, now()->addHours(1));

        return count($recipientUserIds);
    }

    /**
     * Dispatch alert when an early warning is resolved.
     */
    public function dispatchWarningResolvedAlert(
        EarlyWarning $warning,
        ?string $reason = null,
        array $recipientUserIds = []
    ): int {
        $dedupKey = "alert:warning_resolved:{$warning->id}";

        if (Cache::has($dedupKey)) {
            return 0;
        }

        if (empty($recipientUserIds)) {
            $recipientUserIds = $this->resolveRecipientsForWarning($warning);
        }

        if (empty($recipientUserIds)) {
            return 0;
        }

        $eventDto = new NotificationEventDTO(
            eventType: 'early_warning.resolved',
            sourceModule: 'intelligence_automation',
            recipientIds: array_map('strval', $recipientUserIds),
            title: "Early Warning Resolved: {$warning->title}",
            message: $reason ?? "The threat condition associated with this early warning has de-escalated or stabilized.",
            link: "/workspace/monitors?warning={$warning->slug}",
            priority: 'medium',
            metadata: [
                'warning_id' => $warning->id,
                'status' => 'resolved',
            ],
            tenantId: $warning->organization_id,
            idempotencyKey: $dedupKey,
            category: 'early_warning_status'
        );

        $this->notificationEngine->dispatch($eventDto);
        Cache::put($dedupKey, true, now()->addHours(4));

        return count($recipientUserIds);
    }

    /**
     * Dispatch alert when an early warning is reopened after resolution.
     */
    public function dispatchWarningReopenedAlert(
        EarlyWarning $warning,
        ?string $reason = null,
        array $recipientUserIds = []
    ): int {
        $dedupKey = "alert:warning_reopened:{$warning->id}:" . now()->format('YmdH');

        if (Cache::has($dedupKey)) {
            return 0;
        }

        if (empty($recipientUserIds)) {
            $recipientUserIds = $this->resolveRecipientsForWarning($warning);
        }

        if (empty($recipientUserIds)) {
            return 0;
        }

        $eventDto = new NotificationEventDTO(
            eventType: 'early_warning.reopened',
            sourceModule: 'intelligence_automation',
            recipientIds: array_map('strval', $recipientUserIds),
            title: "Early Warning Reopened: {$warning->title}",
            message: $reason ?? "Previously resolved warning reopened due to resurgence in reporting anomalies or new corroboration.",
            link: "/workspace/monitors?warning={$warning->slug}",
            priority: 'high',
            metadata: [
                'warning_id' => $warning->id,
                'status' => 'reopened',
                'reason' => $reason,
            ],
            tenantId: $warning->organization_id,
            idempotencyKey: $dedupKey,
            category: 'early_warning_status'
        );

        $this->notificationEngine->dispatch($eventDto);
        Cache::put($dedupKey, true, now()->addHours(2));

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

        // Multi-tenant: If signal belongs to an organization, notify all organization members
        if ($signal->organization_id) {
            $orgMembers = OrganizationMember::where('organization_id', $signal->organization_id)
                ->pluck('user_id')
                ->map(fn($id) => (string) $id)
                ->all();
            $userIds = array_merge($userIds, $orgMembers);
        }

        // Fallback: If no specific monitors match, notify admin / active users
        if (empty($userIds)) {
            $userIds = User::where('account_type', 'admin')
                ->orWhere('account_type', 'subscriber')
                ->limit(5)
                ->pluck('id')
                ->map(fn($id) => (string) $id)
                ->all();
        }

        return array_values(array_unique($userIds));
    }

    public function resolveRecipientsForWarning(EarlyWarning $warning): array
    {
        $userIds = [];

        if ($warning->signal) {
            $userIds = $this->resolveRecipientsForSignal($warning->signal);
        }

        if ($warning->organization_id) {
            $orgMembers = OrganizationMember::where('organization_id', $warning->organization_id)
                ->pluck('user_id')
                ->map(fn($id) => (string) $id)
                ->all();
            $userIds = array_merge($userIds, $orgMembers);
        }

        if (empty($userIds)) {
            $userIds = User::where('account_type', 'admin')
                ->orWhere('account_type', 'subscriber')
                ->limit(5)
                ->pluck('id')
                ->map(fn($id) => (string) $id)
                ->all();
        }

        return array_values(array_unique($userIds));
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
