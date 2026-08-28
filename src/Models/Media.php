<?php

namespace Mediator\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Mediator\Glide\Thumbnails;
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
 * @property string|null $description
 * @property string|null $caption
 * @property array<string, mixed>|null $exif
 * @property array<int, mixed>|null $curations
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read string|null $url
 * @property-read string $full_path
 * @property-read string $pretty_name
 * @property-read string|null $thumbnail_url
 * @property-read string|null $large_url
 */
class Media extends Model
{
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
        'description',
        'caption',
        'exif',
        'curations',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'url',
        'pretty_name',
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
            'exif' => 'array',
            'curations' => 'array',
        ];
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
                        return $storage->temporaryUrl($this->path, now()->addMinutes(5));
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
