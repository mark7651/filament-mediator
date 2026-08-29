<?php

use Illuminate\Support\Facades\Storage;
use Mediator\Mediator;
use Mediator\Models\Media;
use Mediator\Tests\Fixtures\ProjectMedia;

it('stands on the table named in the config', function () {
    expect((new Media)->getTable())->toBe('media');

    config()->set('mediator.table', 'curator');

    expect((new Media)->getTable())->toBe('curator');
});

it('hands out the model the config names', function () {
    expect(Mediator::model())->toBe(Media::class);

    config()->set('mediator.model', ProjectMedia::class);

    expect(Mediator::model())->toBe(ProjectMedia::class)
        ->and(Mediator::query()->getModel())->toBeInstanceOf(ProjectMedia::class);
});

it('refuses a model the library cannot work with', function () {
    config()->set('mediator.model', stdClass::class);

    Mediator::model();
})->throws(InvalidArgumentException::class);

it('reads the address of a file off its own disk', function () {
    Storage::fake('public');

    $file = Media::query()->create([
        'disk' => 'public',
        'directory' => 'media/2026/08',
        'visibility' => 'public',
        'name' => 'a-file',
        'path' => 'media/2026/08/a-file.webp',
        'width' => 800,
        'height' => 600,
        'size' => 1024,
        'type' => 'image/webp',
        'ext' => 'webp',
    ]);

    expect($file->url)->toBe(Storage::disk('public')->url('media/2026/08/a-file.webp'))
        ->and($file->width)->toBeInt();
});

it('calls a file by its title, and by its file name where there is none', function () {
    $file = new Media(['name' => 'a-file', 'ext' => 'webp']);

    expect($file->prettyName)->toBe('a-file.webp');

    $file->title = 'Договір';

    expect($file->prettyName)->toBe('Договір');
});

it('says of a file only what the row holds, and works the addresses out when asked', function () {
    Storage::fake('public');

    $file = Media::query()->create([
        'disk' => 'public',
        'directory' => 'media/2026/08',
        'visibility' => 'public',
        'name' => 'a-file',
        'path' => 'media/2026/08/a-file.webp',
        'type' => 'image/webp',
        'ext' => 'webp',
    ]);

    // The address of a file is read off its disk, and on a disk that hands out
    // addresses of its own that is a question asked over the network. A record
    // turned into an array for a list of two hundred would ask it two hundred
    // times over, for an answer nothing in the list was waiting for.
    expect($file->toArray())->not->toHaveKey('url')
        ->and($file->toArray())->not->toHaveKey('pretty_name')
        ->and($file->url)->toBe(Storage::disk('public')->url($file->path))
        ->and($file->prettyName)->toBe('a-file.webp');
});
