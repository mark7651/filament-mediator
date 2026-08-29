<?php

declare(strict_types=1);

namespace Mediator\Filament\Forms;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Mediator\Mediator;
use Mediator\Models\Media;
use Mediator\Uploads\Upload;

/**
 * Files of the library standing in a field of a record.
 *
 * The field holds the id of the file and nothing besides, which is what the
 * column behind it holds too, and where it was asked to hold several it holds
 * their ids in the order they were chosen. Choosing happens in the library
 * itself, opened in a modal: the editor meets the same wall, the same search
 * and the same panel of details wherever a file is chosen, and a file uploaded
 * while choosing is chosen the moment it lands.
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

    protected bool|Closure $multiple = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerActions([
            fn (MediaField $field): Action => $field->getChooseAction(),
            fn (MediaField $field): Action => $field->getClearAction(),
        ]);

        // A field holding several files holds a list of them, and a column that
        // has never been filled holds nothing at all: read as one file that is
        // not there, that would be a field showing a card of nothing.
        $this->afterStateHydrated(static function (MediaField $field, mixed $state): void {
            if ($field->isMultiple()) {
                $field->state(array_values(array_filter((array) $state, fn (mixed $id): bool => filled($id))));
            }
        });
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

    /**
     * Whether the field holds several files rather than one.
     *
     * A record showing a run of pictures holds them in a list, so the state of
     * the field is a list as well and the library is opened to gather rather
     * than to hand over the first thing clicked. What the column behind it is
     * made of is the project's business: a cast to an array, a relation saved
     * by the form, anything that takes a list of numbers.
     */
    public function multiple(bool|Closure $condition = true): static
    {
        $this->multiple = $condition;

        return $this;
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->multiple);
    }

    public function getChosen(): ?Media
    {
        $id = $this->getState();

        if (is_array($id) || blank($id)) {
            return null;
        }

        /** @var Media|null */
        return Mediator::query()->find($id);
    }

    /**
     * The files standing in the field, in the order they were chosen rather
     * than in the order the database hands them back.
     *
     * @return list<Media>
     */
    public function getChosenMany(): array
    {
        $ids = array_values(array_filter((array) $this->getState(), fn (mixed $id): bool => filled($id)));

        if ($ids === []) {
            return [];
        }

        /** @var Collection<int, Media> $files */
        $files = Mediator::query()->whereKey($ids)->get()->keyBy(fn (Media $file): string => (string) $file->getKey());

        return array_values(array_filter(array_map(
            fn (mixed $id): ?Media => $files->get((string) $id),
            $ids,
        )));
    }

    /**
     * The file, or the files, the library handed over, written down as the
     * state of the field.
     *
     * The ids come from the browser, so what each of them stands for is read
     * off the record rather than taken on trust: a field that stands for the
     * sign of a record holds a drawing, whatever it was asked to hold.
     *
     * Both names are answered because the library says «id» when it hands over
     * the one file that was opened and «ids» when it hands over everything that
     * was ticked, and either can reach a field: one holding several files is
     * filled by the ticking, and a file uploaded while a single field waits is
     * handed over on its own the moment it lands.
     *
     * @param  array<int, int|string>|null  $ids
     */
    #[ExposedLivewireMethod]
    public function chosen(int|string|null $id = null, ?array $ids = null): void
    {
        $files = $this->allowed($ids ?? ($id === null ? [] : [$id]));

        if ($files === []) {
            return;
        }

        if (! $this->isMultiple()) {
            $this->state($files[0]->getKey());

            return;
        }

        $standing = array_values(array_filter((array) $this->getState(), fn (mixed $one): bool => filled($one)));

        $chosen = array_map(fn (Media $file): int|string => $file->getKey(), $files);

        $this->state(array_values(array_unique([...$standing, ...$chosen])));
    }

    /**
     * One file taken back out of a field that holds several.
     */
    #[ExposedLivewireMethod]
    public function remove(int|string $id): void
    {
        $this->state(array_values(array_filter(
            (array) $this->getState(),
            fn (mixed $one): bool => (string) $one !== (string) $id,
        )));
    }

    /**
     * Of the numbers handed over, the files this field takes, in the order they
     * were handed over.
     *
     * @param  array<int, mixed>  $said
     * @return list<Media>
     */
    private function allowed(array $said): array
    {
        $ids = array_values(array_filter($said, fn (mixed $id): bool => is_int($id) || is_string($id)));

        if ($ids === []) {
            return [];
        }

        /** @var Collection<int, Media> $files */
        $files = Mediator::query()->whereKey($ids)->get()->keyBy(fn (Media $file): string => (string) $file->getKey());

        return array_values(array_filter(array_map(
            function (int|string $id) use ($files): ?Media {
                $file = $files->get((string) $id);

                return $file instanceof Media && in_array((string) $file->type, $this->getTakes(), true)
                    ? $file
                    : null;
            },
            $ids,
        )));
    }

    public function getChooseAction(): Action
    {
        return Action::make('choose')
            ->label(fn (MediaField $component): string => match (true) {
                $component->isMultiple() => __('mediator::media.field.add'),
                $component->getChosen() === null => __('mediator::media.field.choose'),
                default => __('mediator::media.field.change'),
            })
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
                'many' => $component->isMultiple(),
            ]));
    }

    public function getClearAction(): Action
    {
        return Action::make('clear')
            ->label(fn (MediaField $component): string => $component->isMultiple()
                ? __('mediator::media.field.clear_many')
                : __('mediator::media.field.clear'))
            ->icon(Heroicon::XMark)
            ->color('gray')
            ->iconButton()
            ->tooltip(fn (MediaField $component): string => $component->isMultiple()
                ? __('mediator::media.field.clear_many')
                : __('mediator::media.field.clear'))
            ->hidden(fn (MediaField $component): bool => $component->isDisabled())
            ->action(function (MediaField $component): void {
                $component->state($component->isMultiple() ? [] : null);
            });
    }
}
