<?php

namespace Mediator\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Mediator\Glide\Thumbnails;
use Mediator\Observers\MediaObserver;
use Mediator\Uses\Places;
use Throwable;

/**
 * A file of the library.
 *
 * Each record carries the disk it was written to rather than reading the disk
 * of the day out of the config: a project that moves its files elsewhere keeps
 * the addresses of everything written before the move.
 *
 * @property-read int $id
 * @property string $disk
 * @property string|null $directory
 * @property string $visibility
 * @property string $name
 * @property string $path
 * @property int|null $width
 * @property int|null $height
 * @property int|null $size
 * @property string $type
 * @property string $ext
 * @property string|null $alt
 * @property string|null $title
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read string|null $url
 * @property-read string $full_path
 * @property-read string $pretty_name
 * @property-read string|null $thumbnail_url
 * @property-read string|null $large_url
 */
#[ObservedBy(MediaObserver::class)]
class Media extends Model
{
    /**
     * The number of records this file stands in, kept for the several places
     * one card of the library asks for it.
     */
    private ?int $usedBy = null;

    /**
     * The places themselves, kept the same way and for the same reason.
     *
     * @var list<array{kind: string, label: string, url: string|null}>|null
     */
    private ?array $standsIn = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'disk',
        'directory',
        'visibility',
        'name',
        'path',
        'width',
        'height',
        'size',
        'type',
        'ext',
        'alt',
        'title',
    ];

    public function getTable(): string
    {
        return $this->table ?? (string) config('mediator.table', 'media');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'size' => 'integer',
        ];
    }

    /**
     * How many records this file stands in, of every kind at once.
     *
     * The places are the ones the project wrote down in the register, and a
     * project that wrote down none of them counts none: the library warns with
     * what it was told, never with a guess.
     */
    public function usedBy(): int
    {
        return $this->usedBy ??= app(Places::class)->standing($this);
    }

    /**
     * Which records those are, of the ones the register can name. Shorter than
     * the count wherever a project wrote a place down as a number alone.
     *
     * @return list<array{kind: string, label: string, url: string|null}>
     */
    public function standsIn(): array
    {
        return $this->standsIn ??= app(Places::class)->named($this);
    }

    /**
     * The address a file is served at.
     *
     * Visibility is read off the record rather than asked of the driver, which
     * on a remote disk would mean a request over the network for every file of
     * a wall of a hundred.
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $storage = Storage::disk($this->disk);

                if ($this->visibility === 'private') {
                    try {
                        return $storage->temporaryUrl($this->path, now()->addMinutes(self::privateFor()));
                    } catch (Throwable) {
                        // The driver hands out no temporary addresses, so the
                        // plain one is the best there is.
                    }
                }

                return $storage->url($this->path);
            },
        )->shouldCache();
    }

    /**
     * The address of the picture drawn small enough for a wall of them.
     */
    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => app(Thumbnails::class)->url($this, 'thumbnail'),
        )->shouldCache();
    }

    /**
     * The address of the picture drawn for the one card that is open.
     */
    protected function largeUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => app(Thumbnails::class)->url($this, 'large'),
        )->shouldCache();
    }

    /**
     * For how many minutes an address to a file the disk does not serve openly
     * is good.
     */
    public static function privateFor(): int
    {
        $minutes = (int) config('mediator.private_for', 5);

        return $minutes > 0 ? $minutes : 5;
    }

    protected function fullPath(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Storage::disk($this->disk)->path($this->path),
        );
    }

    /**
     * The name a person reads: the title the editor gave the file, or the name
     * it lies under on disk where there is no title.
     */
    protected function prettyName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => filled($this->title) ? (string) $this->title : $this->name.'.'.$this->ext,
        );
    }
}
