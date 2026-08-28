<?php

namespace Mediator\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider as FilamentPanelProvider;
use Mediator\Filament\MediatorPlugin;

/**
 * A panel of a project that has nothing in it but the library, which is the
 * whole of what a project has to do to get one.
 */
class PanelProvider extends FilamentPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(
                MediatorPlugin::make()
                    ->navigationGroup(fn (): string => __('Settings'))
                    ->navigationSort(3),
            );
    }
}
