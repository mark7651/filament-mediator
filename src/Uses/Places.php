<?php

namespace Mediator\Uses;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as SupportCollection;
use Mediator\Models\Media;

/**
 * Every place a file of the library can stand.
 *
 * The library is opened to tidy up, and tidying up is deleting. A picture is
 * chosen from inside the record that needs it, so nothing in the panel says
 * where a file has ended up, and an editor deleting one has no way of knowing
 * what they are taking off the site. This register is the answer: a project
 * writes down the places it puts files in, and the library counts them before
 * it warns.
 *
 * A project that writes nothing down here is a project where nothing is
 * counted. The library still deletes; the warning simply has less to say.
 */
class Places
{
    /**
     * @var list<Closure(Media): int>
     */
    private array $counters = [];

    /**
     * The same places, asked for the records themselves rather than for their
     * number. A place written down as a count alone stands in the number and
     * not in the list, which is why the two are kept apart: the number is
     * asked of every file about to be deleted, and the records only of the one
     * file that is open.
     *
     * @var list<Closure(Media): iterable<int, Model>>
     */
    private array $finders = [];

    /**
     * The same places again, asked which files of the library they hold, all
     * of them at once. Asked of the place rather than of the file because the
     * question is put about the whole library: a wall narrowed to the files
     * nobody stands on would otherwise cost a count per file per place.
     *
     * @var list<Closure(): iterable<int, int|string>>
     */
    private array $holders = [];

    /**
     * The texts of the project that can hold files, by the model they are
     * written in.
     *
     * @var array<class-string<Model>, array{columns: list<string>, stands: Closure(Model): Model}>
     */
    private array $texts = [];

    /**
     * Whether the table of files standing in texts is already written down as
     * a place of its own.
     */
    private bool $reading = false;

    /**
     * A record that holds a file in a column of its own.
     *
     * A record with two columns for files is registered twice, once per
     * column: a practice keeps the cover of its page apart from the icon it is
     * listed by, and either of them alone is reason enough to warn.
     *
     * @param  class-string<Model>  $model
     */
    public function standsIn(string $model, string $column, bool $withTrashed = false): static
    {
        $records = function (Media $file) use ($model, $column, $withTrashed): Builder {
            $records = $model::query()->where($column, $file->getKey());

            if ($withTrashed) {
                $records->withTrashed();
            }

            return $records;
        };

        $this->finders[] = fn (Media $file): Collection => $records($file)->get();

        $this->holders[] = function () use ($model, $column, $withTrashed): SupportCollection {
            $held = $model::query()->whereNotNull($column)->distinct();

            if ($withTrashed) {
                $held->withTrashed();
            }

            return $held->pluck($column);
        };

        return $this->counted(fn (Media $file): int => $records($file)->count());
    }

    /**
     * A text of the project that can hold files inside it, and the columns it
     * is written in.
     *
     * A picture put into a text is held by its address, so there is no column
     * to follow: the text is read when the record is saved and the files found
     * in it are written down. The library hangs that reading on the model
     * itself, so a project has nothing to remember at the moment of saving.
     *
     * The text of a page told in two languages lives in two records while the
     * place a person opens is the one page, so a project says which record
     * stands for the text: `stands` is asked for it, and without it the record
     * that was saved is the record that is named.
     *
     * @param  class-string<Model>  $model
     * @param  list<string>|string  $columns
     * @param  Closure(Model): Model|null  $stands
     */
    public function standsInText(string $model, array|string $columns, ?Closure $stands = null): static
    {
        $columns = (array) $columns;
        $stands ??= fn (Model $record): Model => $record;

        $this->texts[$model] = ['columns' => $columns, 'stands' => $stands];

        $model::saved(function (Model $record) use ($columns, $stands): void {
            Texts::hold($record, $columns, $stands($record));
        });

        $model::deleted(function (Model $record): void {
            // A record waiting in the trash holds the pictures of its text as
            // firmly as one standing on the site, because it is taken out of
            // the trash whole.
            if (method_exists($record, 'isForceDeleting') && ! $record->isForceDeleting()) {
                return;
            }

            Texts::forget($record);
        });

        return $this->readingTheTexts();
    }

    /**
     * The texts written down here, for the command that reads them anew.
     *
     * @return array<class-string<Model>, array{columns: list<string>, stands: Closure(Model): Model}>
     */
    public function texts(): array
    {
        return $this->texts;
    }

    /**
     * The table of files standing in texts, asked the three questions every
     * place is asked. Put in place once, however many texts are written down:
     * they all stand in the one table.
     */
    private function readingTheTexts(): static
    {
        if ($this->reading) {
            return $this;
        }

        $this->reading = true;

        return $this->counted(
            fn (Media $file): int => count($this->holders($file)),
            fn (Media $file): array => $this->records($file),
            anywhere: fn (): array => Texts::query()->distinct()->pluck('media_id')->all(),
        );
    }

    /**
     * The records a file stands in the text of, each of them once however many
     * texts of that record hold it.
     *
     * @return list<array{holder_type: string, holder_id: int}>
     */
    private function holders(Media $file): array
    {
        return Texts::query()
            ->where('media_id', $file->getKey())
            ->select('holder_type', 'holder_id')
            ->distinct()
            ->get()
            ->map(fn (object $held): array => [
                'holder_type' => (string) $held->holder_type,
                'holder_id' => (int) $held->holder_id,
            ])
            ->all();
    }

    /**
     * @return list<Model>
     */
    private function records(Media $file): array
    {
        $records = [];

        foreach (collect($this->holders($file))->groupBy('holder_type') as $type => $held) {
            /** @var class-string<Model>|null $model */
            $model = Relation::getMorphedModel((string) $type) ?? (class_exists((string) $type) ? (string) $type : null);

            if ($model === null) {
                continue;
            }

            foreach ($model::query()->whereKey($held->pluck('holder_id')->all())->get() as $record) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * A place no column leads to, counted by the project itself: a file
     * standing inside the json of a page is found by reading the pages, not by
     * following a relation.
     *
     * A place counted this way is a number and nothing more, unless the
     * project hands over the records as well: pages holding a file inside
     * their json can be named, and then the library names them.
     *
     * A place says which files stand in it only if the project tells it how,
     * and one that says nothing is one the library leaves out of that answer
     * rather than guesses at.
     *
     * @param  Closure(Media): int  $count
     * @param  Closure(Media): iterable<int, Model>|null  $records
     * @param  Closure(): iterable<int, int|string>|null  $anywhere
     */
    public function counted(Closure $count, ?Closure $records = null, ?Closure $anywhere = null): static
    {
        $this->counters[] = $count;

        if ($records !== null) {
            $this->finders[] = $records;
        }

        if ($anywhere !== null) {
            $this->holders[] = $anywhere;
        }

        return $this;
    }

    /**
     * How many records this file stands in, of every kind at once.
     */
    public function standing(Media $file): int
    {
        return array_sum(array_map(
            fn (Closure $count): int => $count($file),
            $this->counters,
        ));
    }

    /**
     * The numbers of every file of the library standing in at least one place.
     *
     * The library is tidied by deleting, and deleting is safe only where
     * nothing stands on the file. That question is asked of the whole wall at
     * once, so it is answered by the places: each names the files it holds,
     * and what is left over is nobody's.
     *
     * A place that cannot name the files it holds is left out of the answer,
     * and its files are counted among nobody's. The register knows what the
     * project told it, and nothing else.
     *
     * @return list<int>
     */
    public function standingAnywhere(): array
    {
        $held = [];

        foreach ($this->holders as $holds) {
            foreach ($holds() as $file) {
                $held[] = (int) $file;
            }
        }

        return array_values(array_unique($held));
    }

    /**
     * Where the file stands, said in words rather than as a number: what each
     * record is, the name it carries and the way to it.
     *
     * An editor about to delete a picture is deciding, and «three records» is
     * not something a decision can be made on while «Practice: Family law» is.
     *
     * @return list<array{kind: string, label: string, url: string|null}>
     */
    public function named(Media $file): array
    {
        $places = [];

        foreach ($this->finders as $records) {
            foreach ($records($file) as $record) {
                $places[] = $this->describe($record);
            }
        }

        return $places;
    }

    /**
     * @return array{kind: string, label: string, url: string|null}
     */
    private function describe(Model $record): array
    {
        /** @var class-string<\Filament\Resources\Resource>|null $resource */
        $resource = rescue(fn (): ?string => Filament::getModelResource($record), null, report: false);

        return [
            'kind' => $resource === null ? class_basename($record) : $resource::getModelLabel(),
            'label' => $this->name($record),
            'url' => $resource === null ? null : $this->way($resource, $record),
        ];
    }

    /**
     * The name a person reads. Asked of the record itself in the words most
     * records are named by, and where it carries none of them, the record is
     * named by its number: a listing that says «Client #12» is still a listing
     * an editor can follow, and a guess at a name is not.
     */
    private function name(Model $record): string
    {
        foreach (['title', 'name', 'heading'] as $said) {
            // Rescued because a model is free to hold a method of that name
            // which is not a relation, and asking for it then throws.
            $name = rescue(fn (): mixed => $record->getAttribute($said), null, report: false);

            if (is_string($name) && filled($name)) {
                return $name;
            }
        }

        return '#'.$record->getKey();
    }

    /**
     * The address of the record in the panel, where there is a page for it and
     * the person looking is allowed to open it.
     *
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    private function way(string $resource, Model $record): ?string
    {
        return rescue(
            fn (): ?string => $resource::canEdit($record)
                ? $resource::getUrl('edit', ['record' => $record])
                : null,
            null,
            report: false,
        );
    }
}
