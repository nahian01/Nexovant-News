<?php

declare(strict_types=1);

namespace App\Services\Publication;

use App\Models\Publication;
use App\Models\PublicationSchedule;
use Illuminate\Support\Carbon;

class PublicationScheduleService
{
    public function __construct(
        protected PublicationGenerationService $generator
    ) {}

    /**
     * Execute all currently due publication schedules.
     *
     * @return array<int, Publication>
     */
    public function runDueSchedules(): array
    {
        $due = PublicationSchedule::where('is_enabled', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                  ->orWhere('next_run_at', '<=', now());
            })
            ->get();

        $generated = [];

        foreach ($due as $schedule) {
            $pub = $this->runSchedule($schedule);
            if ($pub) {
                $generated[] = $pub;
            }
        }

        return $generated;
    }

    /**
     * Execute an individual schedule.
     */
    public function runSchedule(PublicationSchedule|string $schedule): ?Publication
    {
        $scheduleModel = is_string($schedule) ? PublicationSchedule::findOrFail($schedule) : $schedule;

        $dateStr = now()->format('F d, Y');
        $title = str_replace('{date}', $dateStr, $scheduleModel->title_pattern);

        $period = $scheduleModel->frequency === 'weekly'
            ? (now()->subDays(7)->format('M d') . ' – ' . now()->format('M d, Y'))
            : now()->format('F d, Y');

        try {
            $publication = $this->generator->generate([
                'publication_type' => $scheduleModel->publication_type,
                'title' => $title,
                'subtitle' => "Automated {$scheduleModel->frequency} intelligence briefing for strategic subscribers.",
                'reporting_period' => $period,
                'template_id' => $scheduleModel->template_id,
                'visibility' => 'premium',
            ]);

            // If schedule does not require review, auto-publish
            if (! $scheduleModel->require_review) {
                $publication->update([
                    'status' => 'published',
                    'published_at' => now(),
                ]);
            }

            // Calculate next run time
            $nextRun = $scheduleModel->frequency === 'weekly'
                ? now()->addWeek()->setTimeFromTimeString($scheduleModel->schedule_time)
                : now()->addDay()->setTimeFromTimeString($scheduleModel->schedule_time);

            $scheduleModel->update([
                'last_run_at' => now(),
                'next_run_at' => $nextRun,
            ]);

            return $publication;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
