<?php

namespace Mediator;

use Filament\Forms\Components\RichEditor\TipTapExtensions\ImageExtension;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Mediator\Console\Relink;
use Mediator\Filament\Forms\PictureExtension;
use Mediator\Policies\MediaPolicy;
use Mediator\Uses\Places;

class MediatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mediator.php', 'mediator');

        $this->app->singleton(Places::class);

        // A picture in a text is written by the editor of Filament, and it
        // writes the size of the picture into a style on the tag as well as
        // into its attributes. The site the text is shown on lays its own
        // pages out, so the style is taken back out; see the extension itself
        // for why. A project that wants the style back binds this name to the
        // extension of Filament in a provider of its own.
        $this->app->bind(ImageExtension::class, PictureExtension::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/mediator.php');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'mediator');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mediator');

        $this->app->booted(fn () => $this->defaultPolicy());

        if ($this->app->runningInConsole()) {
            $this->commands([Relink::class]);

            $this->publishes([
                __DIR__.'/../config/mediator.php' => config_path('mediator.php'),
            ], 'mediator-config');

            // Published rather than run out of the package, because the table
            // of the library is the project's own: a project that wants a
            // column, an index or a key of its own in it edits the file it was
            // given instead of writing a second migration to alter what it has
            // just raised.
            //
            // Handed over under the names the package holds them by, and never
            // through publishesMigrations(), which stamps the hour of the
            // publishing on them. The table of a library is pointed at by the
            // records of the project, so it has to stand before them: a file
            // dated today lands after every table that already carries a key
            // to it, and a project that raises its database anew is left with
            // a key pointing at nothing. A project that wants it later renames
            // the file itself, which is one deliberate move rather than a trap
            // sprung on everybody.
            $this->publishes([
                __DIR__.'/../database/migrations/0001_01_01_000000_create_mediator_table.php' => database_path('migrations/0001_01_01_000000_create_mediator_table.php'),
                __DIR__.'/../database/migrations/0001_01_01_000001_create_mediator_texts_table.php' => database_path('migrations/0001_01_01_000001_create_mediator_texts_table.php'),
                __DIR__.'/../database/migrations/0001_01_01_000002_index_mediator_table.php' => database_path('migrations/0001_01_01_000002_index_mediator_table.php'),
            ], 'mediator-migrations');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/mediator'),
            ], 'mediator-translations');

            $this->publishes([
                __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/mediator'),
            ], 'mediator-views');
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
