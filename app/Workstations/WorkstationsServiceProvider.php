<?php

namespace App\Workstations;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class WorkstationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'workstations');
    }

    public function boot(): void
    {
        RateLimiter::for('wsm-checkin', fn(Request $r) => Limit::perMinute(config('workstations.checkin_per_minute'))
            ->by($r->ip() . '|' . substr((string)$r->json('workstation_id'), 0, 64)));
        RateLimiter::for('wsm-bootstrap', fn(Request $r) => Limit::perMinute(config('workstations.bootstrap_per_minute'))
            ->by($r->ip()));
        RateLimiter::for('wsm-login', fn(Request $r) => Limit::perMinute(5)->by($r->ip()));

        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'workstations');
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        if ($this->app->runningInConsole()) {
            $this->commands([Console\ReleaseCommand::class]);
        }
    }
}
