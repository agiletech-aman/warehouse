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

Artisan::command('fns-history:refresh', function () {
    $alerts = \App\Support\FnsDetectionHistory::refreshCache();

    if ($alerts === null) {
        $this->error('FNS history fetch failed; see the log.');

        return 1;
    }

    $this->info(count($alerts) . ' history alerts cached.');

    return 0;
})->purpose('Re-fetch the FNS history API (alerts and their image_base64) into the cache');

// Keep the FNS history API cache (30 min TTL) warm so page loads never wait on the full fetch.
Schedule::command('fns-history:refresh')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
// routes/console.php

Schedule::command('readings:delete-unknown')
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->onOneServer();
