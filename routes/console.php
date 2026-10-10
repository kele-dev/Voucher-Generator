<?php

use App\Http\Controllers\HealthController;
use App\Jobs\QueueHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| Driven by one cron entry on the server (see deploy/crontab):
|   * * * * * cd /var/www/voucher && php artisan schedule:run >> /dev/null 2>&1
| Times are in APP_TIMEZONE (Asia/Jakarta).
|
*/

// Liveness signals read by /health.
Schedule::call(fn () => Cache::forever(HealthController::SCHEDULER_KEY, now()->getTimestamp()))
    ->name('heartbeat:scheduler')->everyMinute();
Schedule::job(new QueueHeartbeat)->name('heartbeat:queue')->everyMinute();

// Nightly backup, then an independent check that the newest one is usable.
Schedule::command('voucher:backup')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('voucher:backup:verify')->dailyAt('02:30');

// ZIPs older than retention (rebuildable), orphans, temp folders.
Schedule::command('voucher:prune-exports')->dailyAt('03:00');

// Failed job records older than 30 days. The activity log is never pruned.
Schedule::command('queue:prune-failed', ['--hours' => 720])->dailyAt('03:15');
