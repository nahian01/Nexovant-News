<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\NotificationDeliveryLog;
use App\Models\SubscriberNotificationPreference;
use App\Models\User;
use App\Services\Notification\DTO\NotificationEventDTO;
use App\Services\Notification\NotificationEngine;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendNotificationDigestsCommand extends Command
{
    protected $signature = 'notifications:send-digests {--frequency=daily : Digest frequency (hourly, daily, weekly)}';

    protected $description = 'Dispatch consolidated intelligence digests to subscribers based on their frequency preferences';

    public function handle(NotificationEngine $engine): int
    {
        $frequency = (string) $this->option('frequency');
        $this->info("Executing {$frequency} notification digest processor...");

        $timeWindow = match ($frequency) {
            'hourly' => Carbon::now()->subHour(),
            'weekly' => Carbon::now()->subWeek(),
            default => Carbon::now()->subDay(),
        };

        // Find users who have this digest frequency configured
        $preferences = SubscriberNotificationPreference::where('digest_frequency', $frequency)->get();

        if ($preferences->isEmpty()) {
            $this->line("No subscribers configured for {$frequency} digest frequency.");
            return self::SUCCESS;
        }

        $processedUsers = 0;

        foreach ($preferences as $pref) {
            $user = User::find($pref->user_id);
            if (!$user || !$user->is_active) {
                continue;
            }

            // Fetch unread notifications from the window
            $notifications = Notification::where('user_id', $user->id)
                ->where('created_at', '>=', $timeWindow)
                ->where('category', '!=', 'system')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            if ($notifications->isEmpty()) {
                continue;
            }

            $count = $notifications->count();
            $title = "Nexovant " . ucfirst($frequency) . " Intelligence Digest ({$count} updates)";
            $headlines = $notifications->pluck('title')->take(3)->implode('; ');
            $message = "Your scheduled {$frequency} executive intelligence briefing summary: {$headlines}. Visit your subscriber workspace to review the full verified dossier.";

            $dto = new NotificationEventDTO(
                eventType: "digest.{$frequency}_intelligence",
                sourceModule: 'intelligence',
                recipientIds: [$user->id],
                title: $title,
                message: $message,
                link: '/workspace/notifications',
                priority: 'low',
                metadata: [
                    'digest_frequency' => $frequency,
                    'item_count' => $count,
                ],
                category: 'publication',
                idempotencyKey: "digest:{$frequency}:{$user->id}:" . Carbon::now()->format('Y-m-d-H'),
            );

            $engine->dispatch($dto);
            $processedUsers++;
        }

        $this->info("Completed {$frequency} digest dispatch. Processed {$processedUsers} subscribers.");
        return self::SUCCESS;
    }
}
