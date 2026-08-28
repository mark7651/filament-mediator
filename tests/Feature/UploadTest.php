<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Mediator\Uploads\Upload;

beforeEach(function () {
    Storage::fake('public');

    config()->set('mediator.disk', 'public');
    config()->set('mediator.thumbnails.cache', storage_path('framework/testing/thumbnails'));
});

afterEach(function () {
    File::deleteDirectory(storage_path('framework/testing/thumbnails'));
});

/**
 * A jpeg of the given size lying in a temporary file, handed over the way a
 * browser hands one over.
 */
function photograph(int $width = 1200, int $height = 900, ?int $orientation = null): UploadedFile
{
    $canvas = imagecreatetruecolor($width, $height);
    imagefilledrectangle($canvas, 0, 0, $width - 1, $height - 1, imagecolorallocate($canvas, 200, 60, 40));

    ob_start();
    imagejpeg($canvas, null, 90);
    $bytes = (string) ob_get_clean();
    imagedestroy($canvas);

    if ($orientation !== null) {
        // The note a camera leaves about the way it was held: a big endian
        // tiff header holding one field, spliced in as the first segment
        // after the start of the file.
        $tiff = "MM\0\x2a".pack('N', 8).pack('n', 1)
            .pack('n', 0x0112).pack('n', 3).pack('N', 1).pack('n', $orientation)."\0\0"
            .pack('N', 0);

        $segment = "\xFF\xE1".pack('n', strlen($tiff) + 8)."Exif\0\0".$tiff;

        $bytes = "\xFF\xD8".$segment.substr($bytes, 2);
    }

    $path = tempnam(sys_get_temp_dir(), 'mediator').'.jpg';
    file_put_contents($path, $bytes);

    return new UploadedFile($path, 'Знімок (1).jpg', 'image/jpeg', null, true);
}

function markup(string $svg): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'mediator').'.svg';
    file_put_contents($path, $svg);

    return new UploadedFile($path, 'a-sign.svg', 'image/svg+xml', null, true);
}

it('lays a photograph on the disk as webp, under a name of its own', function () {
    $file = Upload::store(photograph());

    expect($file->ext)->toBe('webp')
        ->and($file->type)->toBe('image/webp')
        ->and($file->path)->toEndWith('.webp')
        ->and($file->title)->toBe('Знімок (1)')
        ->and($file->name)->not->toContain('Знімок')
        ->and(Storage::disk('public')->exists($file->path))->toBeTrue()
        ->and($file->directory)->toBe('media/'.now()->format('Y/m'));
});

it('lays a photograph the way the camera was held, not the way it was written', function () {
    $file = Upload::store(photograph(width: 400, height: 200, orientation: 6));

    expect($file->width)->toBe(200)
        ->and($file->height)->toBe(400);
});

it('brings a picture wider than the library keeps down to that width', function () {
    config()->set('mediator.pictures.longest_side', 100);

    $file = Upload::store(photograph(width: 300, height: 200));

    expect($file->width)->toBe(100)
        ->and($file->height)->toBe(67);
});

it('takes the script out of a drawing before it reaches the disk', function () {
    $file = Upload::store(markup(<<<'SVG'
        <?xml version="1.0" encoding="UTF-8"?>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">
            <script>alert(document.cookie)</script>
            <circle cx="5" cy="5" r="4" onclick="alert(1)" />
        </svg>
        SVG));

    $written = (string) Storage::disk('public')->get($file->path);

    expect($file->ext)->toBe('svg')
        ->and($written)->not->toContain('script')
        ->and($written)->not->toContain('onclick')
        ->and($written)->toContain('circle');
});

it('turns away a file heavier than the library holds, saying which and by how much', function () {
    config()->set('mediator.ceilings.image', 1024);

    $check = Validator::make(['file' => photograph()], ['file' => Upload::rules()]);

    expect($check->fails())->toBeTrue()
        ->and($check->errors()->first('file'))->toContain('Знімок (1).jpg');

    config()->set('mediator.ceilings.image', 10 * 1024 * 1024);

    expect(Validator::make(['file' => photograph()], ['file' => Upload::rules()])->fails())->toBeFalse();
});

it('replaces the file behind a record without moving the record', function () {
    $file = Upload::store(photograph());

    $was = $file->path;
    $drawn = $file->thumbnailUrl;

    $this->get($drawn)->assertOk();

    $folder = storage_path('framework/testing/thumbnails/public/'.$was);

    expect(File::isDirectory($folder))->toBeTrue();

    $again = Upload::replace($file->fresh(), photograph(width: 300, height: 300));

    expect($again->id)->toBe($file->id)
        ->and($again->path)->not->toBe($was)
        ->and($again->title)->toBe($file->title)
        ->and(Storage::disk('public')->exists($was))->toBeFalse()
        ->and(File::isDirectory($folder))->toBeFalse();
});

it('takes the file off the disk when the record of it goes', function () {
    $file = Upload::store(photograph());
    $path = $file->path;

    $file->delete();

    expect(Storage::disk('public')->exists($path))->toBeFalse();
});
