<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Mediator\Livewire\MediaLibrary;
use Mediator\Models\Media;
use Mediator\Tests\Fixtures\Person;
use Mediator\Uploads\Upload;

it('holds the ceilings the library refuses a file above', function () {
    expect(config('mediator.ceilings.image'))->toBe(10 * 1024 * 1024)
        ->and(config('mediator.ceilings.default'))->toBe(100 * 1024 * 1024);
});

it('holds the disk, the folder and the visibility new files are written with', function () {
    expect(config('mediator.disk'))->toBe('public')
        ->and(config('mediator.directory'))->toBe('media')
        ->and(config('mediator.visibility'))->toBe('public');
});

it('takes the kinds of file the project says it takes', function () {
    config()->set('mediator.types', ['image/png' => 'png', 'application/epub+zip' => 'epub']);

    expect(Upload::takes())->toBe(['image/png', 'application/epub+zip'])
        ->and(Upload::rules()[1])->toBe('mimetypes:image/png,application/epub+zip');
});

it('keeps a picture as it arrived where the project asks for nothing to be redrawn', function () {
    Storage::fake('public');

    config()->set('mediator.pictures.redraw', []);

    $photograph = UploadedFile::fake()->createWithContent(
        'znimok.jpg',
        (string) ImageManager::gd()->create(40, 30)->toJpeg(),
    );

    $file = Upload::store($photograph);

    expect($file->type)->toBe('image/jpeg')
        ->and($file->ext)->toBe('jpg')
        ->and(Storage::disk('public')->size($file->path))->toBe($photograph->getSize());
});

it('puts on the wall as many cards at a time as the project asks for', function () {
    $this->actingAs(new Person);

    config()->set('mediator.step', 2);

    collect(range(1, 5))->each(fn (int $number) => Media::query()->create([
        'disk' => 'public',
        'directory' => 'media/2026/08',
        'visibility' => 'public',
        'name' => 'kartynka-'.$number,
        'path' => 'media/2026/08/kartynka-'.$number.'.webp',
        'type' => 'image/webp',
        'ext' => 'webp',
    ]));

    livewire(MediaLibrary::class)
        ->assertSet('shown', 2)
        ->assertSee(__('mediator::media.more', ['count' => 3]))
        ->call('loadMore')
        ->assertSet('shown', 4);
});

it('holds how far the wall grows and how it is searched', function () {
    expect(config('mediator.step'))->toBe(24)
        ->and(config('mediator.wall'))->toBe(240)
        // The reading is what a library gets until the project has raised the
        // index and said so, because the index answers differently and a
        // library whose answers changed under it on an upgrade would be a
        // library nobody trusts.
        ->and(config('mediator.search'))->toBe('like');
});
