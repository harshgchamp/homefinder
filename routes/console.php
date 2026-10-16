<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| Production runs `php artisan schedule:run` every minute (cron, or a
| scheduler container). Laravel then decides which of these are due.
| Locally: `php artisan schedule:work`. List them: `php artisan schedule:list`.
|
*/

// Unpublish listings live for 90+ days that are still available.
Schedule::command('properties:archive-stale')
    ->dailyAt('02:00')
    ->withoutOverlapping()  // skip if yesterday's run is somehow still going
    ->onOneServer();        // with several app servers, only one of them runs it

// Permanently delete properties that have been in the trash for 30+ days
// (Property::prunable()), including their image files via the observer.
Schedule::command('model:prune')->dailyAt('03:00')->onOneServer();

// Failed jobs are kept in the failed_jobs table so they can be inspected and
// retried (queue:failed, queue:retry). After a week, remove them.
Schedule::command('queue:prune-failed', ['--hours' => 168])->daily()->onOneServer();
