<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\DeleteUnknownReadings;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('devices:mark-stale-offline --minutes=30')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Schedule::command('fns-detections:sync-locations')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Keep the FNS history API cache (30 min TTL) warm so page loads never wait on the full fetch.
Schedule::call(fn () => \App\Support\FnsDetectionHistory::refreshCache())
    ->name('fns-history:refresh-cache')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
// routes/console.php

Schedule::command('readings:delete-unknown')
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->onOneServer();
