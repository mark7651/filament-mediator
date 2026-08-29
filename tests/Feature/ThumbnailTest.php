<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mediator\Glide\Thumbnails;
use Mediator\Models\Media;

beforeEach(function () {
    Storage::fake('public');

    config()->set('mediator.thumbnails.cache', storage_path('framework/testing/thumbnails'));
});

afterEach(function () {
    File::deleteDirectory(storage_path('framework/testing/thumbnails'));
});

function picture(string $path = 'media/2026/08/a-file.png'): Media
{
    $canvas = imagecreatetruecolor(1200, 900);
    imagefilledrectangle($canvas, 0, 0, 1199, 899, imagecolorallocate($canvas, 30, 90, 180));

    ob_start();
    imagepng($canvas);
    $bytes = (string) ob_get_clean();
    imagedestroy($canvas);

    Storage::disk('public')->put($path, $bytes);

    return Media::query()->create([
        'disk' => 'public',
        'directory' => dirname($path),
        'visibility' => 'public',
        'name' => pathinfo($path, PATHINFO_FILENAME),
        'path' => $path,
        'width' => 1200,
        'height' => 900,
        'size' => strlen($bytes),
        'type' => 'image/png',
        'ext' => 'png',
    ]);
}

it('draws a picture down to the size the wall shows it at', function () {
    $file = picture();

    expect($file->thumbnailUrl)->toStartWith('/mediator/pictures/'.$file->path)
        ->and($file->thumbnailUrl)->toContain('w=200');

    $answer = $this->get($file->thumbnailUrl);

    $answer->assertOk();

    expect($answer->headers->get('Content-Type'))->toBe('image/webp')
        ->and((int) $answer->headers->get('Content-Length'))->toBeLessThan($file->size);
});

it('turns away an address that is not signed, and one signed with another key', function () {
    $file = picture();

    $this->get('/mediator/pictures/'.$file->path.'?w=200&h=200&fit=crop&fm=webp')->assertForbidden();

    $forged = str_replace('&s=', '&s=0', $file->thumbnailUrl);

    $this->get($forged)->assertForbidden();
});

it('draws the open card larger than the card of the wall', function () {
    $file = picture();

    expect($file->largeUrl)->toContain('w=1024')
        ->and($file->largeUrl)->not->toBe($file->thumbnailUrl);
});

it('hands a file it cannot redraw the address of the file itself', function () {
    $file = Media::query()->create([
        'disk' => 'public',
        'directory' => 'media/2026/08',
        'visibility' => 'public',
        'name' => 'a-sign',
        'path' => 'media/2026/08/a-sign.svg',
        'size' => 512,
        'type' => 'image/svg+xml',
        'ext' => 'svg',
    ]);

    expect($file->thumbnailUrl)->toBe($file->url)
        ->and($file->largeUrl)->toBe($file->url)
        ->and(app(Thumbnails::class)->redraws($file))->toBeFalse();
});

it('leaves the address of the file itself as direct as it was', function () {
    $file = picture();

    expect($file->url)->toBe(Storage::disk('public')->url($file->path))
        ->and($file->url)->not->toContain('/mediator/pictures/');
});

it('lets an address to a file the disk does not serve openly run out', function () {
    $file = picture('media/2026/08/pryvatna.png');

    $file->update(['visibility' => 'private']);

    $address = (string) $file->thumbnailUrl;

    // The file itself is served under an address that lasts minutes, and a
    // picture drawn off it is the same picture: an address to it that never ran
    // out would outlive the one to the file and hand it to anybody it was ever
    // pasted to.
    expect($address)->toContain('expires=');

    $this->get($address)->assertOk();

    $this->travel(Media::privateFor() + 1)->minutes();

    $this->get($address)->assertForbidden();
});

it('leaves the address of an openly served picture good for as long as the file stands', function () {
    expect((string) picture()->thumbnailUrl)->not->toContain('expires=');
});

it('draws the same picture whenever the address to it was made', function () {
    $file = picture('media/2026/08/pryvatna.png');

    $file->update(['visibility' => 'private']);

    $this->get((string) $file->thumbnailUrl)->assertOk();

    $drawn = File::allFiles(storage_path('framework/testing/thumbnails'));

    $this->travel(1)->minutes();

    // The hour an address runs out at is not one of the measures a picture is
    // drawn by, so a second address to the same picture is answered with the
    // one already drawn rather than filling the disk with a copy a minute.
    $this->get((string) $file->fresh()?->thumbnailUrl)->assertOk();

    expect(File::allFiles(storage_path('framework/testing/thumbnails')))->toHaveCount(count($drawn));
});
