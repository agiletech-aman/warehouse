<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\DeleteUnknownReadings;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('devices:mark-stale-offline')
    ->everyThirtyMinutes()
    ->withoutOverlapping();
// routes/console.php

Schedule::command('readings:delete-unknown')
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->onOneServer();