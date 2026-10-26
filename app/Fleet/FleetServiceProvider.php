<?php

namespace App\Fleet;

use Illuminate\Support\ServiceProvider;

class FleetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'fleet');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'fleet');
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        if ($this->app->runningInConsole()) {
            $this->commands([Console\FleetRelease::class]);
        }
    }
}
