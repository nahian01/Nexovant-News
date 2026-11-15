<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Scheduled Ingestion & Automation Pipeline
Schedule::command('nexovant:ingest-feeds')->everyFifteenMinutes()->withoutOverlapping();

// System housekeeping & security token pruning
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('queue:prune-failed --hours=168')->weekly();
Schedule::command('sanctum:prune-expired --hours=48')->daily();
Schedule::call(function () {
    \App\Models\PublicationDownloadToken::where('expires_at', '<', now()->subDays(7))->delete();
})->daily();
