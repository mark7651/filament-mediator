<?php

namespace Mediator\Uploads;

use Closure;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Mediator\Glide\Thumbnails;
use Mediator\Mediator;
use Mediator\Models\Media;
use RuntimeException;

/**
 * A file dropped on the library, put where the library keeps its files.
 *
 * The name on disk is a uuid and the name the editor reads is the name the file
 * came with: a file called «Договір (1).pdf» has to keep that name in the panel
 * while never becoming part of an address.
 *
 * Files are laid out in folders by year and month under the folder of the
 * config, so a directory listing of a few hundred files stays something a
 * person can open.
 */
class Upload
{
    /**
     * The kinds of file the library holds where the project says nothing, and
     * what each of them is called on disk.
     *
     * The name a file arrives with says nothing true about it: a picture may be
     * handed over as page.html, and a disk that took that name would serve a
     * script from the domain the panel is signed in to. So the type is read out
     * of the bytes and the extension is written from this list.
     *
     * A project says its own list in mediator.types; this one stands behind it
     * for a project whose published config was written before the key existed.
     *
     * @var array<string, string>
     */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/wav' => 'wav',
        'audio/ogg' => 'ogg',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    /**
     * The kinds the library holds and the extension each is written with, as
     * the project has them.
     *
     * @return array<string, string>
     */
    public static function types(): array
    {
        $types = config('mediator.types', self::EXTENSIONS);

        return is_array($types) && $types !== [] ? $types : self::EXTENSIONS;
    }

    /**
     * @return list<string>
     */
    public static function takes(): array
    {
        return array_keys(self::types());
    }

    /**
     * The kinds the library draws again on the way in instead of keeping as
     * they arrived.
     *
     * @return list<string>
     */
    private static function redraws(): array
    {
        $redraw = config('mediator.pictures.redraw', ['image/jpeg', 'image/png']);

        return is_array($redraw) ? array_values($redraw) : [];
    }

    /**
     * How heavy a file of this kind may be.
     */
    public static function ceiling(string $type): int
    {
        $ceilings = config('mediator.ceilings');

        return (int) (str_starts_with($type, 'image/')
            ? $ceilings['image']
            : $ceilings['default']);
    }

    /**
     * What an incoming file is held to.
     *
     * The kinds are decided here rather than in the browser, where the accept
     * attribute of an input is a suggestion. The files lie on the same domain
     * as the panel, so anything a browser would run as a page of that domain
     * has no business on the disk.
     *
     * Where a field opened the library, what it takes is what that field
     * takes: a wall that shows only svg and png would otherwise still swallow
     * a film dropped on it.
     *
     * @param  list<string>  $takes
     * @return array<int, mixed>
     */
    public static function rules(array $takes = []): array
    {
        $kinds = $takes === [] ? self::takes() : $takes;

        return ['file', 'mimetypes:'.implode(',', $kinds), self::withinItsCeiling()];
    }

    public static function store(UploadedFile $file): Media
    {
        /** @var Media */
        return Mediator::query()->create([
            ...self::put($file),
            'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
        ]);
    }

    /**
     * A new picture behind a file the site already shows.
     *
     * The record stays where it is, so nothing that points at it has to be
     * found and changed, and the name the editor gave the file stays with the
     * record rather than with the picture that has just gone.
     *
     * The old file leaves the disk only once the record points at the new one:
     * a record showing a file that is not there is worse than a file nothing
     * shows. The address changes, because the name on disk is a new one, and
     * every size drawn off the old file is dropped with it.
     */
    public static function replace(Media $file, UploadedFile $new): Media
    {
        $disk = (string) $file->disk;
        $gone = (string) $file->path;

        $file->update(self::put($new));

        Storage::disk($disk)->delete($gone);
        app(Thumbnails::class)->forget($disk, $gone);

        return $file;
    }

    /**
     * The file put where the library keeps its files, described the way the
     * table of the library describes it.
     *
     * @return array{
     *     disk: string,
     *     directory: string,
     *     visibility: string,
     *     name: string,
     *     path: string,
     *     width: int|null,
     *     height: int|null,
     *     size: int,
     *     type: string,
     *     ext: string,
     * }
     */
    private static function put(UploadedFile $file): array
    {
        $disk = (string) config('mediator.disk');
        $visibility = (string) config('mediator.visibility');
        $directory = trim((string) config('mediator.directory'), '/').'/'.now()->format('Y/m');
        $storage = Storage::disk($disk);

        $type = (string) $file->getMimeType();
        $extension = self::types()[$type] ?? null;
        $name = (string) Str::uuid();

        if ($extension === null) {
            throw new RuntimeException('The library does not hold files of type '.$type.'.');
        }

        // A photograph is not kept as the camera wrote it: what goes on the
        // disk is the picture turned the right way up, brought down to a size a
        // page has use for and written as webp. The file that arrived is gone,
        // and everything the record says is said about the file that stayed.
        $picture = self::redrawn($type, (string) $file->getRealPath());

        if ($picture !== null) {
            $type = 'image/webp';
            $extension = self::types()['image/webp'] ?? 'webp';
        }

        $path = $directory.'/'.$name.'.'.$extension;

        if ($picture !== null) {
            $quality = (int) config('mediator.pictures.quality');

            $storage->put($path, (string) $picture->toWebp($quality), $visibility);

            $width = $picture->width();
            $height = $picture->height();
        } else {
            // Handed over as it stands rather than read into memory first: a
            // film of a hundred megabytes has no business inside a php string.
            $stored = $file->storeAs($directory, $name.'.'.$extension, ['disk' => $disk, 'visibility' => $visibility]);

            if ($stored === false) {
                throw new RuntimeException('The library could not write '.$file->getClientOriginalName().' to the '.$disk.' disk.');
            }

            // Measured on the temporary upload, which is on this machine, while
            // the library disk need not be.
            [$width, $height] = self::measure((string) $file->getRealPath());
        }

        // Markup is served as it stands rather than redrawn, so a script inside
        // it would run on the domain of the panel.
        if ($type === 'image/svg+xml') {
            $markup = $storage->get($path);

            if ($markup !== null) {
                $storage->put($path, self::withoutScripts($markup), $visibility);
            }
        }

        return [
            'disk' => $disk,
            'directory' => $directory,
            'visibility' => $visibility,
            'name' => $name,
            'path' => $path,
            'width' => $width,
            'height' => $height,
            'size' => $storage->size($path),
            'type' => $type,
            'ext' => $extension,
        ];
    }

    /**
     * A picture ready for the disk, or nothing where the file is one the
     * library keeps as it arrived.
     *
     * Turning the picture is the reader's own doing: it obeys the note the
     * camera left in EXIF. That note does not survive into the webp, which is
     * the point, because a file on disk should not need a reader willing to
     * obey it to be shown the right way up.
     */
    private static function redrawn(string $type, string $path): ?ImageInterface
    {
        if (! in_array($type, self::redraws(), true)) {
            return null;
        }

        $side = (int) config('mediator.pictures.longest_side');

        return ImageManager::gd()->read($path)->scaleDown($side, $side);
    }

    /**
     * The same markup with everything a browser would run taken out of it.
     */
    private static function withoutScripts(string $markup): string
    {
        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);

        $clean = $sanitizer->sanitize($markup);

        // Markup nothing can parse comes back as false. An empty file is
        // written rather than the one that arrived, because a file that cannot
        // be read cannot be said to be safe either.
        return $clean === false ? '' : $clean;
    }

    /**
     * How heavy this file in particular may be, said in words the editor can
     * act on rather than as a rule of the framework.
     */
    private static function withinItsCeiling(): Closure
    {
        return function (string $attribute, mixed $file, Closure $fail): void {
            if (! $file instanceof UploadedFile) {
                return;
            }

            $ceiling = self::ceiling((string) $file->getMimeType());

            if ($file->getSize() > $ceiling) {
                $fail(__('mediator::media.refused.weight', [
                    'name' => $file->getClientOriginalName(),
                    'limit' => intdiv($ceiling, 1024 * 1024),
                ]));
            }
        };
    }

    /**
     * The size of a picture, where the file is one and the server can read it.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private static function measure(string $path): array
    {
        $size = @getimagesize($path);

        return $size === false ? [null, null] : [$size[0], $size[1]];
    }
}
