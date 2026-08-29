<?php

namespace Mediator\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mediator\Uses\Places;
use Mediator\Uses\Texts;

/**
 * The texts of the project read anew, and the files standing in them written
 * down from scratch.
 *
 * The table of pairs is kept by the saving of a record, which is where a text
 * changes as long as a person changes it. A text changed past the model, by an
 * import or by a statement written by hand, leaves the table saying what was
 * true before, and this command is the way back to the truth.
 */
class Relink extends Command
{
    protected $signature = 'mediator:relink';

    protected $description = 'Read the texts of the project anew and write down the files standing in them';

    public function handle(Places $places): int
    {
        $texts = $places->texts();

        if ($texts === []) {
            $this->components->warn('No text of this project is written down as one that can hold files.');

            return self::SUCCESS;
        }

        foreach ($texts as $model => $where) {
            $records = $model::query();

            // A record in the trash holds the pictures of its text, so it is
            // read here as well.
            if (in_array(SoftDeletes::class, class_uses_recursive($model), strict: true)) {
                $records->withTrashed();
            }

            $read = 0;

            $records->chunkById(200, function (Collection $chunk) use ($where, &$read): void {
                $chunk->each(function (Model $record) use ($where, &$read): void {
                    Texts::hold($record, $where['columns'], $where['stands']($record));
                    $read++;
                });
            });

            $this->components->info($model.': '.$read.' read');
        }

        return self::SUCCESS;
    }
}
