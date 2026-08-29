<?php

declare(strict_types=1);

namespace Mediator\Filament\Forms;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Mediator\Mediator;
use Mediator\Models\Media;
use Mediator\Uploads\Upload;

/**
 * One file of the library standing in a field of a record.
 *
 * The field holds the id of the file and nothing besides, which is what the
 * column behind it holds too. Choosing happens in the library itself, opened in
 * a modal: the editor meets the same wall, the same search and the same panel
 * of details wherever a file is chosen, and a file uploaded while choosing is
 * chosen the moment it lands.
 */
class MediaField extends Field
{
    protected string $view = 'mediator::field';

    /**
     * The kinds of file this field takes. Empty stands for everything the
     * library holds.
     *
     * @var list<string>
     */
    protected array $takes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerActions([
            fn (MediaField $field): Action => $field->getChooseAction(),
            fn (MediaField $field): Action => $field->getClearAction(),
        ]);
    }

    /**
     * @param  list<string>  $kinds
     */
    public function takes(array $kinds): static
    {
        $this->takes = $kinds;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getTakes(): array
    {
        return $this->takes === [] ? Upload::takes() : $this->takes;
    }

    public function getChosen(): ?Media
    {
        $id = $this->getState();

        if (blank($id)) {
            return null;
        }

        /** @var Media|null */
        return Mediator::query()->find($id);
    }

    /**
     * The file the library handed over, written down as the state of the field.
     *
     * The id comes from the browser, so what kind of file it is gets read off
     * the record rather than taken on trust: a field that stands for the sign
     * of a record holds a drawing, whatever it was asked to hold.
     */
    #[ExposedLivewireMethod]
    public function chosen(int|string $id): void
    {
        $file = Mediator::query()->find($id);

        if (! $file instanceof Media || ! in_array((string) $file->type, $this->getTakes(), true)) {
            return;
        }

        $this->state($file->getKey());
    }

    public function getChooseAction(): Action
    {
        return Action::make('choose')
            ->label(fn (MediaField $component): string => $component->getChosen() === null
                ? __('mediator::media.field.choose')
                : __('mediator::media.field.change'))
            ->icon(Heroicon::Photo)
            ->button()
            ->color('gray')
            ->hidden(fn (MediaField $component): bool => $component->isDisabled())
            ->modalHeading(__('mediator::media.plural_title'))
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->modalWidth(Width::SevenExtraLarge)
            ->modalContent(fn (MediaField $component): View => view('mediator::field-library', [
                'key' => $component->getKey(),
                'takes' => $component->getTakes(),
            ]));
    }

    public function getClearAction(): Action
    {
        return Action::make('clear')
            ->label(__('mediator::media.field.clear'))
            ->icon(Heroicon::XMark)
            ->color('gray')
            ->iconButton()
            ->tooltip(__('mediator::media.field.clear'))
            ->hidden(fn (MediaField $component): bool => $component->isDisabled())
            ->action(function (MediaField $component): void {
                $component->state(null);
            });
    }
}
