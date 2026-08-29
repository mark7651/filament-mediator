<?php

namespace Mediator;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Mediator\Models\Media;

/**
 * The way in to the record of a file.
 *
 * The class is asked for rather than named, so that a project can put its own
 * model in place of the one the package ships and have the library work with
 * that one everywhere.
 *
 * The two things a project may say about the records themselves are said here
 * as well: which of them it is willing to show, and what it writes into a row
 * the library is about to create. Both are held in the container rather than in
 * a static of the class, so that they last exactly as long as the application
 * that set them and no test, queue worker or second request inherits what
 * another one said.
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
     * How much of the library this project is willing to show.
     *
     * The library is one wall for everything in the table, which is right for a
     * site and wrong for a place where each account holds files of its own. A
     * project that has to divide it says so here, in a provider, and every wall
     * it opens is narrowed the same way: the section, the field of a record and
     * the button of the editor all read the library through this.
     *
     *     Mediator::scope(fn (Builder $files) => $files->whereBelongsTo(Filament::getTenant()));
     *
     * The narrowing is a way of showing, never a way of keeping: a file outside
     * it is not hidden from the disk, and a project that has to keep one
     * account out of the files of another writes that into its policy, which is
     * what the library asks before it acts on a file.
     *
     * @param  Closure(Builder<Media>): mixed|null  $scope
     */
    public static function scope(?Closure $scope): void
    {
        app()->instance('mediator.scope', $scope);
    }

    /**
     * What the project writes into a row of the library besides what the file
     * itself says.
     *
     * The library knows a file: the disk, the sides, the kind, the weight. Who
     * it belongs to, where it came from and what the project calls it are
     * questions the library cannot answer and a column of the project may
     * insist on, so a project answers them here and the answer is written in
     * the same statement as the rest.
     *
     *     Mediator::filling(fn (array $said, UploadedFile $file): array => [
     *         'account_id' => auth()->user()?->account_id,
     *     ]);
     *
     * What comes back is added to what the library said, so a project can
     * override any of it as well. The same hook is asked when a new file is put
     * behind a record that is already there.
     *
     * @param  Closure(array<string, mixed>, UploadedFile): array<string, mixed>|null  $filling
     */
    public static function filling(?Closure $filling): void
    {
        app()->instance('mediator.filling', $filling);
    }

    /**
     * What the library says about a file, with what the project adds to it.
     *
     * @param  array<string, mixed>  $said
     * @return array<string, mixed>
     */
    public static function fill(array $said, UploadedFile $file): array
    {
        $filling = app()->bound('mediator.filling') ? app('mediator.filling') : null;

        return $filling instanceof Closure ? [...$said, ...$filling($said, $file)] : $said;
    }

    /**
     * The library as this project shows it.
     *
     * @return Builder<Media>
     */
    public static function query(): Builder
    {
        $query = static::unscoped();
        $scope = app()->bound('mediator.scope') ? app('mediator.scope') : null;

        if ($scope instanceof Closure) {
            $scope($query);
        }

        return $query;
    }

    /**
     * The whole table, whatever the project shows of it.
     *
     * Asked for where the question is about a file rather than about a library:
     * the row a file is written into, the record behind an address that was
     * signed by this application, the files a text of the project holds. None
     * of those is a wall somebody is looking at, and several of them are asked
     * outside any session at all, where a scope written around the person
     * signed in would answer with nothing.
     *
     * @return Builder<Media>
     */
    public static function unscoped(): Builder
    {
        return static::model()::query();
    }
}
