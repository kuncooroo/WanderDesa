<?php

use App\Jobs\DetectOfflineDevices;
use App\Jobs\ExpireUnpaidOrders;
use App\Jobs\ExpireUnusedTickets;
use App\Jobs\ReconcileOpenPayments;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler (SRS-SCH-01 / SRS-DEP-02)
|--------------------------------------------------------------------------
|
| Production/staging VPS cron (every minute):
|   * * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
|
| Also run a queue worker — these tasks dispatch ShouldQueue jobs:
|   php artisan queue:work --sleep=1 --tries=1
|
| Local: `php artisan schedule:work` plus a worker (or QUEUE_CONNECTION=sync).
|
*/

Schedule::job(new ExpireUnpaidOrders)
    ->everyMinute()
    ->withoutOverlapping(5)
    ->name('expire-unpaid-orders');

Schedule::job(new ReconcileOpenPayments)
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->name('reconcile-open-payments');

Schedule::job(new DetectOfflineDevices)
    ->everyMinute()
    ->withoutOverlapping(5)
    ->name('detect-offline-devices');

Schedule::job(new ExpireUnusedTickets)
    ->hourly()
    ->withoutOverlapping(55)
    ->name('expire-unused-tickets');

Schedule::command('ops:backup-mysql')
    ->dailyAt('02:15')
    ->withoutOverlapping(120)
    ->name('backup-mysql');
