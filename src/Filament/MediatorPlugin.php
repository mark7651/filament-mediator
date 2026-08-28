<?php

namespace Mediator\Filament;

use BackedEnum;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Mediator\Filament\Pages\Library;
use UnitEnum;

/**
 * The library put into a panel.
 *
 * One registration brings the section, the wall behind it and the styles it is
 * drawn with. Where the section stands in the sidebar is the panel's business
 * rather than the package's, so the group, the icon and the order are asked
 * for here and nothing else is.
 */
class MediatorPlugin implements Plugin
{
    protected string|BackedEnum|Htmlable|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected string|UnitEnum|Closure|null $navigationGroup = null;

    protected ?int $navigationSort = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'mediator';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                Library::class,
            ])
            // The wall, the cards and the panel of details are drawn by this
            // package, so they are styled by it as well: a project that
            // registers the plugin has nothing to add to its build.
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): View => view('mediator::styles'),
            );
    }

    public function boot(Panel $panel): void {}

    /**
     * The group of the sidebar the section stands in. Taken as a closure as
     * well, because a group named in the language of the panel cannot be read
     * while the panel is still being built.
     */
    public function navigationGroup(string|UnitEnum|Closure|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationIcon(string|BackedEnum|Htmlable|null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return value($this->navigationGroup);
    }

    public function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->navigationIcon;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }
}
