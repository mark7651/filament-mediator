<?php

namespace Mediator;

use Illuminate\Support\ServiceProvider;

class MediatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mediator.php', 'mediator');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/mediator.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mediator.php' => config_path('mediator.php'),
            ], 'mediator-config');
        }
    }
}
