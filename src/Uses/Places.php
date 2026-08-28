<?php

namespace Mediator\Uses;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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

        return $this->counted(fn (Media $file): int => $records($file)->count());
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
     * @param  Closure(Media): int  $count
     * @param  Closure(Media): iterable<int, Model>|null  $records
     */
    public function counted(Closure $count, ?Closure $records = null): static
    {
        $this->counters[] = $count;

        if ($records !== null) {
            $this->finders[] = $records;
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
