<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Scheduled Ingestion & Automation Pipeline
$settingsPath = storage_path('app/settings.json');
$intervalMinutes = 15;
if (\Illuminate\Support\Facades\File::exists($settingsPath)) {
    $settings = json_decode(\Illuminate\Support\Facades\File::get($settingsPath), true) ?? [];
    $intervalMinutes = (int) ($settings['feed_fetch_interval_minutes'] ?? $settings['rss_polling_interval_minutes'] ?? 15);
}
$intervalMinutes = max(1, min(1440, $intervalMinutes));

Schedule::command('nexovant:ingest-feeds')
    ->cron("*/{$intervalMinutes} * * * *")
    ->withoutOverlapping();
Schedule::call(function () {
    app(\App\Services\Ingestion\AutoIngestSchedulerService::class)->runDueIngestionCycles();
})->name('auto_ingest_due_evaluator')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('intelligence:evaluate-monitors')->everyFifteenMinutes()->withoutOverlapping(30)->onOneServer();
Schedule::command('discovery:evaluate-saved-searches')->hourly()->withoutOverlapping(60)->onOneServer();

// System housekeeping & security token pruning
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('queue:prune-failed --hours=168')->weekly();
Schedule::command('sanctum:prune-expired --hours=48')->daily();
Schedule::call(function () {
    \App\Models\PublicationDownloadToken::where('expires_at', '<', now()->subDays(7))->delete();
})->daily();
