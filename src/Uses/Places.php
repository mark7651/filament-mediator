<?php

namespace Mediator\Uses;

use Closure;
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
        return $this->counted(function (Media $file) use ($model, $column, $withTrashed): int {
            $records = $model::query()->where($column, $file->getKey());

            if ($withTrashed) {
                $records->withTrashed();
            }

            return $records->count();
        });
    }

    /**
     * A place no column leads to, counted by the project itself: a file
     * standing inside the json of a page is found by reading the pages, not by
     * following a relation.
     *
     * @param  Closure(Media): int  $count
     */
    public function counted(Closure $count): static
    {
        $this->counters[] = $count;

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
}
