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

    /**
     * How many cards the wall holds at most, where the project says nothing.
     */
    private const WALL = 240;

    /**
     * The shortest word a full-text index holds, which is what MySQL is set to
     * out of the box.
     */
    private const LETTERS = 3;

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

    /**
     * How many cards of the library the wall starts after.
     *
     * Nought while the wall is growing, which is what it does until it reaches
     * its ceiling; from there on the wall moves along the library a windowful
     * at a time instead of growing, and this is where the window stands.
     */
    #[Locked]
    public int $from = 0;

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
     * How many cards the wall holds at most.
     *
     * Everything standing on the wall is drawn anew and sent anew on every
     * deed of the library, so a wall that grew without end would come to weigh
     * a megabyte a click. Never below one step, because a wall that cannot
     * hold what it opens with is not a wall.
     */
    private function ceiling(): int
    {
        return max((int) config('mediator.wall', self::WALL), $this->step());
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
        $this->shown = min($this->shown + $this->step(), $this->ceiling());
    }

    /**
     * The window moved on to the files older than the ones standing on it,
     * which is what the foot of the wall offers once it has grown as far as it
     * grows.
     */
    public function further(): void
    {
        $this->page($this->from + $this->shown);
    }

    /**
     * And back to the newer ones, a whole windowful at a time.
     */
    public function back(): void
    {
        $this->page(max($this->from - $this->ceiling(), 0));
    }

    /**
     * The wall moved to another part of the library. It opens there the way it
     * opens anywhere: one step of cards, nothing ticked, nothing open, and the
     * eye at the top of it rather than at the foot it was reading a moment ago.
     */
    private function page(int $from): void
    {
        $this->reopen();
        $this->from = $from;

        $this->dispatch('media-paged');
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
        $wall = $this->query()->skip($this->from)->take($this->shown)->get();

        // Counted only where the wall came back full, because a wall holding
        // less than it was asked for is the whole of what there is and the
        // count would say so a second time.
        $left = $wall->count() < $this->shown
            ? 0
            : max($this->query()->count() - $this->from - $wall->count(), 0);
        $growing = $this->shown < $this->ceiling();
        $canDelete = Gate::allows('deleteAny', Mediator::model());

        return view('mediator::library', [
            'wall' => $wall,
            'left' => $left,
            // The wall grows into the places of the cards on their way only
            // while it is growing at all: a wall at its ceiling is replaced
            // rather than added to, and places held for cards that are not
            // coming would be holes in it.
            'growing' => $growing,
            'coming' => $growing ? min($this->step(), $left) : 0,
            'open' => $this->openId === null ? null : $this->file($this->openId),
            'canDelete' => $canDelete,
            // An empty wall says one of two things, and which of them it says
            // is the difference between a library nothing has been put into
            // and a library the editor has narrowed down to nothing.
            'narrowed' => $this->search !== '' || filled($this->type) || $this->unused || $this->from > 0,
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
            ->when($this->search !== '', fn (Builder $query): Builder => $this->found($query))
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
     * The wall narrowed to what was typed into the search.
     *
     * The picture itself cannot be searched, so what is searched is everything
     * the library knows in words: the name of the file, the name it was given,
     * and what it shows for those who cannot see it.
     *
     * Those three are read letter by letter through the whole table, or asked
     * of a full-text index where the project says its library has grown big
     * enough to want one. The two answer differently, which is why it is the
     * project that chooses: a run of letters inside a word is found by the
     * reading and not by the index, and two words in either order are found by
     * the index and not by the reading.
     *
     * @param  Builder<Media>  $query
     * @return Builder<Media>
     */
    private function found(Builder $query): Builder
    {
        $words = $this->words($query->getConnection()->getDriverName());

        if ($words === []) {
            $like = '%'.$this->search.'%';

            return $query->where(fn (Builder $query): Builder => $query
                ->where('name', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhere('alt', 'like', $like));
        }

        // Every word has to stand somewhere in the file, and the end of each is
        // left open, so a name typed half way finds it.
        return $query->whereFullText(
            ['name', 'title', 'alt'],
            implode(' ', array_map(fn (string $word): string => '+'.$word.'*', $words)),
            ['mode' => 'boolean'],
        );
    }

    /**
     * The words of the search, where there is an index to ask with them.
     *
     * Nothing comes back from a project that asked for the reading, from a
     * database that keeps no such index, and from a search too short for one to
     * hold: a word of one or two letters is not written into a full-text index
     * at all, and asking with it would answer nothing where the reading
     * answers.
     *
     * @return list<string>
     */
    private function words(string $driver): array
    {
        if (config('mediator.search') !== 'words' || ! in_array($driver, ['mysql', 'mariadb'], true)) {
            return [];
        }

        /** @var list<string> $words */
        $words = preg_split('/[^\p{L}\p{N}_]+/u', $this->search, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($words as $word) {
            if (mb_strlen($word) < self::LETTERS) {
                return [];
            }
        }

        return $words;
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
        $this->from = 0;
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
