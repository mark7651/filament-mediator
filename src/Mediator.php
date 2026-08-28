<?php

namespace Mediator;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Mediator\Models\Media;

/**
 * The way in to the record of a file.
 *
 * The class is asked for rather than named, so that a project can put its own
 * model in place of the one the package ships and have the library work with
 * that one everywhere.
 */
class Mediator
{
    /**
     * @return class-string<Media>
     */
    public static function model(): string
    {
        $model = config('mediator.model', Media::class);

        if (! is_string($model) || ($model !== Media::class && ! is_subclass_of($model, Media::class))) {
            throw new InvalidArgumentException('The model named in mediator.model has to be '.Media::class.' or a class inheriting it.');
        }

        return $model;
    }

    /**
     * @return Builder<Media>
     */
    public static function query(): Builder
    {
        return static::model()::query();
    }
}
