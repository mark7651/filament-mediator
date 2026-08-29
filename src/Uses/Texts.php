<?php

namespace Mediator\Uses;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Mediator\Mediator;

/**
 * The files standing inside the texts of a project, written down as they are
 * put there.
 *
 * A picture in a text is held by its address and not by a column of its own, so
 * there is nothing to follow and nothing to count. The answer is written down
 * instead: the text is read once, at the moment the record is saved, and the
 * files found in it are kept in a table of pairs. Counting a place then costs a
 * key of an index, whatever the texts of the project have grown to.
 *
 * Reading the texts anew on every question would work as well and would need no
 * table at all, but it reads every text of the project each time it is asked,
 * and the texts of a site only grow.
 */
class Texts
{
    /**
     * The table of pairs, named in the config so a project can hold it
     * wherever it holds the rest of its tables.
     */
    public static function table(): string
    {
        return (string) config('mediator.texts_table', 'media_in_texts');
    }

    public static function query(): Builder
    {
        return DB::table(self::table());
    }

    /**
     * The text of this record read anew, and the files found in it written
     * down under the record a person is shown.
     *
     * @param  list<string>  $columns
     */
    public static function hold(Model $source, array $columns, Model $holder): void
    {
        $files = [];

        foreach ($columns as $column) {
            $files = [...$files, ...self::inside((string) $source->getAttribute($column))];
        }

        self::forget($source);

        if ($files === []) {
            return;
        }

        self::query()->insert(array_map(fn (int $file): array => [
            'media_id' => $file,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'holder_type' => $holder->getMorphClass(),
            'holder_id' => $holder->getKey(),
        ], array_values(array_unique($files))));
    }

    /**
     * Everything this record was holding, let go of: the record itself is gone
     * for good, or its text is about to be written down anew.
     */
    public static function forget(Model $source): void
    {
        self::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->delete();
    }

    /**
     * The files a text holds, found by the addresses standing in it.
     *
     * Both the picture and the link are read, because a text holds a file
     * either way: a photograph is put in as a picture and a contract as a link
     * to it.
     *
     * The address is matched by the name of the file alone, which the library
     * writes as a uuid, so a text pointing at a file of another site cannot
     * name a file of this one by accident. A library carried over from
     * elsewhere may hold two files of one name, and then both are counted: a
     * file counted as standing is never the one deleted by mistake.
     *
     * @return list<int>
     */
    public static function inside(string $text): array
    {
        if ($text === '') {
            return [];
        }

        preg_match_all('/(?:src|href)\s*=\s*["\']([^"\']+)["\']/i', $text, $found);

        $names = [];

        foreach ($found[1] as $address) {
            $name = pathinfo((string) parse_url(html_entity_decode($address), PHP_URL_PATH), PATHINFO_FILENAME);

            if ($name !== '') {
                $names[] = urldecode($name);
            }
        }

        if ($names === []) {
            return [];
        }

        return Mediator::query()
            ->whereIn('name', array_values(array_unique($names)))
            ->pluck('id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();
    }
}
