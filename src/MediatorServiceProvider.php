<?php

namespace Mediator;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Mediator\Policies\MediaPolicy;
use Mediator\Uses\Places;

class MediatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mediator.php', 'mediator');

        $this->app->singleton(Places::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/mediator.php');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'mediator');

        $this->app->booted(fn () => $this->defaultPolicy());

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mediator.php' => config_path('mediator.php'),
            ], 'mediator-config');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/mediator'),
            ], 'mediator-translations');
        }
    }

    /**
     * The policy the package ships, put in place only where the project has
     * none of its own for the record of a file.
     *
     * Left until the whole application has booted, because the policy of a
     * project is registered by a provider of the project, and those boot after
     * the providers of the packages they use.
     */
    public function defaultPolicy(): void
    {
        $model = Mediator::model();

        if (Gate::getPolicyFor($model) !== null) {
            return;
        }

        Gate::policy($model, MediaPolicy::class);
    }
}
