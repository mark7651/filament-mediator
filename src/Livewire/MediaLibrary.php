<?php

namespace Mediator\Livewire;

use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Intervention\Image\Exceptions\DecoderException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Mediator\Mediator;
use Mediator\Models\Media;
use Mediator\Uploads\Upload;
use Mediator\Uses\Places;

/**
 * The library as one wall of files with a panel of details beside it.
 *
 * Written as a component of its own rather than as a page of the panel because
 * the same wall is opened from three places: its own section, the field of a
 * record that holds a file, and the button of the editor that puts a picture
 * into the text. A library that behaves differently depending on where it was
 * opened from is two libraries to learn and two to keep working.
 *
 * The wall grows on demand instead of turning pages: what is being looked for
 * is a picture, and a picture is recognised by looking rather than by reading a
 * page number.
 */
class MediaLibrary extends Component
{
    use WithFileUploads;

    /**
     * How many cards are added to the wall at a time, and how many stand on it
     * when it opens, where the project says nothing.
     */
    private const STEP = 24;

    public string $search = '';

    /**
     * One of image, video, audio, document, or nothing for the whole library.
     */
    public ?string $type = null;

    /**
     * Whether the wall is narrowed to the files no record of the site stands
     * on, which are the files that can be swept out without breaking a page.
     */
    public bool $unused = false;

    #[Locked]
    public int $shown = 0;

    #[Locked]
    public ?int $openId = null;

    /**
     * The files ticked for deleting together.
     *
     * Held under lock, as everything the wall itself writes down is: the ticks
     * are put there by a card that was clicked, and a list of numbers arriving
     * from the browser instead would be a list of files somebody chose without
     * ever seeing the wall they stand on.
     *
     * @var list<int>
     */
    #[Locked]
    public array $chosen = [];

    /**
     * What is being uploaded right now. Livewire fills this and empties it
     * again as soon as the files are in the library.
     *
     * @var list<TemporaryUploadedFile>
     */
    public array $files = [];

    /**
     * The file dropped on the open one to stand in its place.
     */
    public ?TemporaryUploadedFile $replacement = null;

    /**
     * Whether the library was opened to choose a file for a field of a record
     * rather than to be looked through on its own page.
     */
    #[Locked]
    public bool $picking = false;

    /**
     * Whether the library gathers several files at once rather than handing
     * over the one that was opened. The text of a page asks for this: a run of
     * pictures goes into it as one deed instead of opening the library again
     * for each of them.
     */
    #[Locked]
    public bool $many = false;

    /**
     * Whether the gathering is for the text of a page rather than for a field
     * of a record. The two are the same wall and the same ticking, and the
     * button under them says what each is for: a picture is put into a text
     * and a file is taken into a field.
     */
    #[Locked]
    public bool $intoText = false;

    /**
     * The kinds of file the wall is narrowed to, where the field that opened
     * the library takes only some of what the library holds. Empty stands for
     * everything.
     *
     * @var list<string>
     */
    #[Locked]
    public array $takes = [];

    /**
     * The two things a person may say about a file, held apart from the record
     * so the panel of details is a form rather than a list.
     */
    public ?string $title = null;

    public ?string $alt = null;

    /**
     * Asked of the config rather than held as a number of the class, so a
     * project whose files are heavy on the eye can put fewer of them on the
     * wall at once.
     */
    private function step(): int
    {
        $step = (int) config('mediator.step', self::STEP);

        return $step > 0 ? $step : self::STEP;
    }

    /**
     * The wall opens on one step of cards. Filled here rather than where the
     * property stands, because the number is the project's to say and a
     * property cannot ask.
     */
    public function boot(): void
    {
        if ($this->shown < 1) {
            $this->shown = $this->step();
        }
    }

    /**
     * The library as a place to look through is opened by whoever the project
     * lets look through it.
     *
     * Asked once the component stands rather than in boot(), because what it
     * asks about is how the wall was opened and that is only known after the
     * mounting. A wall opened to choose a file for a field is left to the form
     * above it: the person is already inside a record they were allowed to
     * open, and the file they pick is written by that form and nowhere else.
     */
    public function booted(): void
    {
        if (! $this->picking) {
            Gate::authorize('viewAny', Mediator::model());
        }
    }

    public function updatedSearch(): void
    {
        $this->reopen();
    }

    public function updatedType(): void
    {
        $this->reopen();
    }

    public function updatedUnused(): void
    {
        $this->reopen();
    }

    /**
     * A further step of the wall, asked for by the scroll that reaches the foot
     * of it and by the button standing there as well. The button is what keeps
     * the rest of the library reachable from the keyboard, where there is no
     * scrolling to watch.
     */
    public function loadMore(): void
    {
        $this->shown += $this->step();
    }

    public function open(int $id): void
    {
        $file = $this->file($id);

        if ($file === null) {
            return;
        }

        $this->openId = (int) $file->id;
        $this->title = $file->title;
        $this->alt = $file->alt;
    }

    public function close(): void
    {
        $this->openId = null;
        $this->title = null;
        $this->alt = null;
    }

    /**
     * The card after the open one, which on a wall of the newest first is the
     * older file.
     */
    public function next(): void
    {
        $this->walk('<');
    }

    public function previous(): void
    {
        $this->walk('>');
    }

    public function saveDetails(): void
    {
        $file = $this->file($this->openId);

        if ($file === null) {
            return;
        }

        Gate::authorize('update', $file);

        $file->update([
            'title' => blank($this->title) ? null : $this->title,
            'alt' => blank($this->alt) ? null : $this->alt,
        ]);

        Notification::make()->success()->title(__('mediator::media.renamed'))->send();
    }

    public function delete(int $id): void
    {
        $file = $this->file($id);

        if ($file === null) {
            return;
        }

        Gate::authorize('delete', $file);

        $file->delete();

        $this->forget($id);

        Notification::make()->success()->title(__('mediator::media.deleted', ['count' => 1]))->send();
    }

    /**
     * Clearing out the ticked files.
     *
     * Deleted one record at a time on purpose: the file leaves the disk through
     * the observer of the package, which hangs off the model, and a single
     * statement over the whole set would leave every file where it was.
     *
     * Every one of them is asked about by itself before any of them goes. The
     * button is offered on the strength of deleteAny, which is a question about
     * the person and not about a file, and a project whose policy lets an
     * editor delete some files and not others would otherwise have that policy
     * answered once for a whole armful. Asked before the first deletion rather
     * than as each comes up, so a refusal in the middle does not leave half the
     * armful gone.
     */
    public function deleteChosen(): void
    {
        /** @var Collection<int, Media> $files */
        $files = Mediator::query()->whereKey($this->chosen)->get();

        if ($files->isEmpty()) {
            return;
        }

        Gate::authorize('deleteAny', Mediator::model());

        $files->each(fn (Media $file) => Gate::authorize('delete', $file));

        $files->each(function (Media $file): void {
            $file->delete();
            $this->forget((int) $file->id);
        });

        Notification::make()->success()->title(__('mediator::media.deleted', ['count' => $files->count()]))->send();
    }

    public function toggle(int $id): void
    {
        $this->chosen = in_array($id, $this->chosen, strict: true)
            ? array_values(array_diff($this->chosen, [$id]))
            : [...$this->chosen, $id];
    }

    public function clearChosen(): void
    {
        $this->chosen = [];
    }

    /**
     * Everything dropped on the wall goes into the library as it is dropped, and
     * the newest of it stands first when the wall draws itself again.
     */
    public function updatedFiles(): void
    {
        Gate::authorize('create', Mediator::model());

        $this->validate([
            'files.*' => Upload::rules($this->takes),
        ], [
            'files.*.mimetypes' => __('mediator::media.refused.type'),
        ]);

        $taken = [];

        foreach ($this->files as $file) {
            $one = $this->take($file);

            if ($one !== null) {
                $taken[] = (int) $one->id;
            }
        }

        $this->files = [];

        if (! $this->picking || $taken === []) {
            return;
        }

        // A file uploaded while choosing is what the editor came for, so the
        // field gets it without being asked to pick it off the wall as well.
        // Where several are being gathered it joins the ticked ones instead and
        // waits there for the rest.
        if ($this->many) {
            $this->chosen = [...$this->chosen, ...$taken];

            return;
        }

        $this->choose((int) end($taken));
    }

    /**
     * A new picture put behind the open file, which is how a photograph is
     * changed everywhere it stands at once.
     */
    public function updatedReplacement(): void
    {
        $file = $this->file($this->openId);

        if ($file === null || $this->replacement === null) {
            return;
        }

        Gate::authorize('update', $file);

        $this->validate([
            'replacement' => Upload::rules($this->takes),
        ], [
            'replacement.mimetypes' => __('mediator::media.refused.type'),
        ]);

        if ($this->take($this->replacement, $file) !== null) {
            Notification::make()->success()->title(__('mediator::media.replaced'))->send();
        }

        $this->replacement = null;
    }

    /**
     * The open file handed to the field the library was opened for.
     *
     * The library writes nothing down: it says which file was chosen and the
     * field of the record is what holds the choice, because the library knows
     * nothing about records.
     */
    public function choose(int $id): void
    {
        $this->dispatch('media-chosen', id: $id);
    }

    /**
     * The ticked files handed over together, in the order they were ticked,
     * which is the order they will stand in in the text.
     */
    public function chooseMany(): void
    {
        if ($this->chosen === []) {
            return;
        }

        $this->dispatch('media-chosen', ids: $this->chosen);
    }

    /**
     * One file into the library, either as a record of its own or behind a
     * record that is already there. Gives back the record it went into, or
     * nothing where it did not go in at all.
     */
    private function take(TemporaryUploadedFile $file, ?Media $standingIn = null): ?Media
    {
        try {
            $taken = $standingIn === null
                ? Upload::store($file)
                : Upload::replace($standingIn, $file);
        } catch (DecoderException) {
            // A file that says it is a picture and holds nothing anything can
            // read. Told about one by one rather than refused as a whole, so
            // the rest of what was dropped still goes in.
            Notification::make()
                ->danger()
                ->title(__('mediator::media.refused.broken', ['name' => $file->getClientOriginalName()]))
                ->send();

            return null;
        }

        return $taken;
    }

    public function render(): View
    {
        $wall = $this->query()->take($this->shown)->get();

        // Counted only where the wall came back full, because a wall holding
        // less than it was asked for is the whole of what there is and the
        // count would say so a second time.
        $left = $wall->count() < $this->shown
            ? 0
            : max($this->query()->count() - $wall->count(), 0);
        $canDelete = Gate::allows('deleteAny', Mediator::model());

        return view('mediator::library', [
            'wall' => $wall,
            'left' => $left,
            'coming' => min($this->step(), $left),
            'open' => $this->openId === null ? null : $this->file($this->openId),
            'canDelete' => $canDelete,
            // An empty wall says one of two things, and which of them it says
            // is the difference between a library nothing has been put into
            // and a library the editor has narrowed down to nothing.
            'narrowed' => $this->search !== '' || filled($this->type) || $this->unused,
            'standingChosen' => $canDelete ? $this->standingChosen() : 0,
        ]);
    }

    /**
     * How many of the ticked files stand somewhere, which is what the warning
     * of a mass deletion says instead of naming the places of fifty files.
     */
    private function standingChosen(): int
    {
        if ($this->chosen === [] || $this->many) {
            return 0;
        }

        return app(Places::class)->held(Mediator::query()->whereKey($this->chosen))->count();
    }

    /**
     * @return Builder<Media>
     */
    private function query(): Builder
    {
        return Mediator::query()
            ->when($this->search !== '', function (Builder $query): void {
                $like = '%'.$this->search.'%';

                // The picture itself cannot be searched, so what is searched is
                // everything the library knows in words: the name of the file,
                // the name it was given, and what it shows for those who cannot
                // see it.
                $query->where(fn (Builder $query): Builder => $query
                    ->where('name', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('alt', 'like', $like));
            })
            // A field may take an svg or a png and nothing else, and a wall of
            // files that cannot be chosen is a wall of dead ends.
            ->when($this->takes !== [], fn (Builder $query): Builder => $query->whereIn('type', $this->takes))
            // Asked with filled() rather than against null: the option that
            // stands for the whole library carries an empty value, and Livewire
            // hands that over as it is.
            ->when(filled($this->type), fn (Builder $query): Builder => $this->ofType($query))
            // Asked of the register only where the wall is narrowed to them:
            // the answer costs a condition per registered place, and a wall
            // that is not being tidied has no use for it.
            ->when($this->unused, fn (Builder $query): Builder => app(Places::class)->free($query))
            ->orderByDesc('id');
    }

    /**
     * @param  Builder<Media>  $query
     * @return Builder<Media>
     */
    private function ofType(Builder $query): Builder
    {
        // A document is everything that is not a picture, a film or a sound:
        // the list of what a project may hand over is open, while those three
        // are not.
        return match ($this->type) {
            'image', 'video', 'audio' => $query->where('type', 'like', $this->type.'/%'),
            default => $query
                ->where('type', 'not like', 'image/%')
                ->where('type', 'not like', 'video/%')
                ->where('type', 'not like', 'audio/%'),
        };
    }

    /**
     * The record behind a number that came from the browser, where the library
     * shows it and the person looking is allowed to see it.
     *
     * Every deed of the wall goes through here, so the answer to «may this
     * person see this file» is given in one place: the wall never opens, walks
     * to, renames or deletes a record it would not have shown in the first
     * place.
     */
    private function file(?int $id): ?Media
    {
        if ($id === null) {
            return null;
        }

        $file = Mediator::query()->find($id);

        return $file instanceof Media && $this->mayBeSeen($file) ? $file : null;
    }

    /**
     * Whether this person may be shown this file.
     *
     * A project that says nothing about looking at one file in particular is
     * not a project that hides files, and asking a policy for an ability it
     * never wrote would answer no to every file in the library at once. So the
     * question is put only where the policy of the project has an answer to
     * it, and a project that has to keep one person out of the files of
     * another writes a view() and is asked it here.
     */
    private function mayBeSeen(Media $file): bool
    {
        $policy = Gate::getPolicyFor($file);

        if ($policy === null) {
            return true;
        }

        if (method_exists($policy, 'view') || method_exists($policy, '__call')) {
            return Gate::allows('view', $file);
        }

        // A policy that says nothing about one file in particular may still
        // say something about every ability at once. A before() that answers
        // is an answer here as well, and one that stands aside leaves the file
        // where the query put it. Asked the way the gate itself asks it.
        $person = auth()->user();

        $said = $person !== null && method_exists($policy, 'before')
            ? $policy->before($person, 'view', $file)
            : null;

        return $said === null || (bool) $said;
    }

    /**
     * Moves the open file by one step along the wall as it stands now, so the
     * search and the filter decide what «beside» means.
     *
     * Asked of the database as the one file beside this one rather than as the
     * whole wall to be counted through: the wall is ordered by the key, so the
     * card after the open one is the nearest key under it and the card before
     * it the nearest key over, and either is an index away however long the
     * library has grown.
     */
    private function walk(string $side): void
    {
        if ($this->openId === null) {
            return;
        }

        $files = $this->query();
        $key = $files->getModel()->getQualifiedKeyName();

        $beside = $files
            ->where($key, $side, $this->openId)
            ->reorder($key, $side === '<' ? 'desc' : 'asc')
            ->first();

        if ($beside !== null) {
            $this->open((int) $beside->getKey());
        }
    }

    /**
     * A wall asked anew stands as it did when it opened: the ticks go with it,
     * because a file ticked under the previous search is a file the editor can
     * no longer see and would delete without meaning to.
     */
    private function reopen(): void
    {
        $this->shown = $this->step();
        $this->close();

        // While the ticks stand for what goes into the text they survive the
        // search instead: the pictures of one text are looked for one at a
        // time, and a search that emptied the gathering would make the second
        // picture cost the first.
        if (! $this->many) {
            $this->chosen = [];
        }
    }

    private function forget(int $id): void
    {
        $this->chosen = array_values(array_diff($this->chosen, [$id]));

        if ($this->openId === $id) {
            $this->close();
        }
    }
}
