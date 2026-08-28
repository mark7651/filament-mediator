<?php

namespace Mediator\Observers;

use Illuminate\Support\Facades\Storage;
use Mediator\Glide\Thumbnails;
use Mediator\Models\Media;

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
    }
}
