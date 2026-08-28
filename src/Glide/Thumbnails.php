<?php

namespace Mediator\Glide;

use Illuminate\Support\Facades\Storage;
use League\Glide\Server;
use League\Glide\ServerFactory;
use League\Glide\Signatures\Signature;
use League\Glide\Urls\UrlBuilder;
use Mediator\Models\Media;

/**
 * The pictures of the library drawn to the size they are looked at.
 *
 * A wall of a hundred files cannot hand out the files themselves: a photograph
 * off a camera is several megabytes and the wall shows it two hundred pixels
 * wide. Glide draws the smaller picture on the first request and keeps it, and
 * every address carries a signature so that nobody outside can ask for a
 * thousand sizes of the same file and fill the disk with them.
 */
class Thumbnails
{
    /**
     * The kinds of picture Glide redraws. An svg is a picture too, but it is
     * markup, and markup is served as it stands.
     *
     * @var list<string>
     */
    public const REDRAWN = ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'avif', 'gif'];

    /**
     * The address a file is looked at under at the given size.
     *
     * A file Glide cannot redraw, a film or a document or an svg, answers with
     * its own address, so a wall built out of these never has a hole in it.
     */
    public function url(Media $file, string $size): ?string
    {
        if (! $this->redraws($file)) {
            return $file->url;
        }

        return (new UrlBuilder($this->path(), new Signature($this->token())))
            ->getUrl($file->path, $this->measures($size));
    }

    public function redraws(Media $file): bool
    {
        return in_array(mb_strtolower((string) $file->ext), self::REDRAWN, true);
    }

    /**
     * The Glide server reading off the disk a file was written to.
     *
     * The name of the disk prefixes the cache, so two files sharing a path on
     * two different disks are not drawn over one another.
     */
    public function server(string $disk): Server
    {
        return (new ServerFactory([
            'source' => Storage::disk($disk)->getDriver(),
            'cache' => (string) config('mediator.thumbnails.cache'),
            'cache_path_prefix' => $disk,
            'max_image_size' => (int) config('mediator.thumbnails.max_image_size'),
            'response' => new StreamedResponseFactory(request()),
        ]))->getServer();
    }

    /**
     * Drop every drawn size of a file, which is what a file replaced under the
     * same record needs: the address stays and the picture behind it changed.
     */
    public function forget(Media $file): void
    {
        $this->server($file->disk)->deleteCache($file->path);
    }

    public function path(): string
    {
        return trim((string) config('mediator.thumbnails.path', 'mediator/pictures'), '/');
    }

    /**
     * The key the signature is made with. Where a project sets none, the key of
     * the application stands in: it is a secret of the same standing and it is
     * there from the first day.
     */
    public function token(): string
    {
        $token = config('mediator.thumbnails.token');

        return filled($token) ? (string) $token : (string) config('app.key');
    }

    /**
     * @return array<string, string|int>
     */
    private function measures(string $size): array
    {
        /** @var array<string, array<string, string|int>> $sizes */
        $sizes = config('mediator.thumbnails.sizes', []);

        return $sizes[$size] ?? [];
    }
}
