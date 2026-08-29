<?php

namespace Mediator\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Livewire\LivewireServiceProvider;
use Mediator\MediatorServiceProvider;
use Mediator\Tests\Fixtures\PanelProvider;
use Mediator\Tests\Fixtures\Person;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
    }

    /**
     * The tables of the library, raised the way a project raises them: the
     * package hands its migrations over to be published rather than running
     * them itself, so the bench holds them the way a project would.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');
    }

    /**
     * The whole of a Filament panel, because the library is a section of one
     * and a section is only worth testing where the panel around it stands.
     *
     * Named in the order composer discovers them by, which is the order of the
     * names of the packages. Filament binds the data store of Livewire to one
     * of its own, and Livewire binds the same name to a single instance: put
     * the wrong way round, the store is built anew on every read and a
     * component loses everything it wrote down.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            ActionsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            LivewireServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            MediatorServiceProvider::class,
            PanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', Person::class);
        $app['config']->set('view.paths', [
            ...$app['config']->get('view.paths', []),
            __DIR__.'/Fixtures/views',
        ]);

        // Livewire cuts every upload at twelve megabytes of its own accord,
        // which is less than the library takes. The ceiling belongs to the
        // host of the library rather than to the library, so the bench raises
        // it the way the readme tells a project to.
        $app['config']->set('livewire.temporary_file_upload.rules', ['required', 'file', 'max:102400']);
    }
}
