<?php

namespace Mediator\Observers;

use Illuminate\Support\Facades\Storage;
use Mediator\Glide\Thumbnails;
use Mediator\Models\Media;
use Mediator\Uses\Texts;

class MediaObserver
{
    /**
     * A record gone takes its file with it, and every size drawn off that file.
     * The library is opened to tidy up, and a disk quietly keeping everything
     * ever deleted is the opposite of that.
     */
    public function deleted(Media $file): void
    {
        Storage::disk($file->disk)->delete($file->path);

        app(Thumbnails::class)->forget((string) $file->disk, (string) $file->path);

        // The texts that held it are not touched: the picture leaves the page
        // it stood on either way, and rewriting the prose of a project behind
        // the back of the person who wrote it would be worse. What goes is the
        // record of the holding, which now says something that is not true.
        Texts::query()->where('media_id', $file->getKey())->delete();
    }
}
