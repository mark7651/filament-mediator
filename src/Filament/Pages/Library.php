<?php

namespace Mediator\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Mediator\Filament\MediatorPlugin;
use Mediator\Mediator;
use UnitEnum;

/**
 * The library as a section of the panel.
 *
 * The page carries nothing of its own: the wall, the search and the panel of
 * details are one Livewire component, because the same library is opened from
 * the field of a record and from the editor as well, and it has to behave the
 * same in all three places.
 */
class Library extends Page
{
    protected string $view = 'mediator::page';

    protected static ?string $slug = 'media';

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return MediatorPlugin::get()->getNavigationIcon();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return MediatorPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return MediatorPlugin::get()->getNavigationSort();
    }

    public static function getNavigationLabel(): string
    {
        return __('mediator::media.plural_title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('mediator::media.plural_title');
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', Mediator::model());
    }
}
