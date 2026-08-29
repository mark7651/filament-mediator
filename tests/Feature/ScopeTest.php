<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mediator\Livewire\MediaLibrary;
use Mediator\Mediator;
use Mediator\Models\Media;
use Mediator\Tests\Fixtures\Article;
use Mediator\Tests\Fixtures\ArticleForm;
use Mediator\Tests\Fixtures\Person;

beforeEach(function () {
    Storage::fake('public');

    $this->actingAs(new Person);
});

function fileOf(string $account, string $name = 'obkladynka'): Media
{
    /** @var Media */
    return Media::query()->create([
        'disk' => 'public',
        'directory' => 'media/2026/08',
        'visibility' => 'public',
        'name' => $name,
        'path' => 'media/2026/08/'.$name.'.jpg',
        'width' => 1200,
        'height' => 800,
        'size' => 204800,
        'type' => 'image/jpeg',
        'ext' => 'jpg',
        'alt' => $account,
    ]);
}

/**
 * The library of one account and nothing else, said the way a project says it
 * in a provider of its own.
 */
function onlyTheFilesOf(string $account): void
{
    Mediator::scope(fn (Builder $files) => $files->where('alt', $account));
}

it('shows the whole table where the project asks for nothing', function () {
    fileOf('zoria', 'persha');
    fileOf('viter', 'druha');

    livewire(MediaLibrary::class)
        ->assertSee('persha')
        ->assertSee('druha');
});

it('narrows the wall to what the project shows of the library', function () {
    fileOf('zoria', 'nasha');
    fileOf('viter', 'chuzha');

    onlyTheFilesOf('zoria');

    livewire(MediaLibrary::class)
        ->assertSee('nasha')
        ->assertDontSee('chuzha');
});

it('leaves a file outside the narrowing out of reach of the wall', function () {
    $theirs = fileOf('viter', 'chuzha');

    Storage::disk('public')->put($theirs->path, 'file');

    onlyTheFilesOf('zoria');

    livewire(MediaLibrary::class)
        ->call('open', $theirs->id)
        ->assertSet('openId', null)
        ->call('delete', $theirs->id)
        ->call('toggle', $theirs->id)
        ->call('deleteChosen');

    expect(Media::query()->count())->toBe(1);

    Storage::disk('public')->assertExists($theirs->path);
});

it('narrows the wall a field of a record opens as well', function () {
    fileOf('zoria', 'nasha');
    fileOf('viter', 'chuzha');

    onlyTheFilesOf('zoria');

    livewire(MediaLibrary::class, ['picking' => true])
        ->assertSee('nasha')
        ->assertDontSee('chuzha');
});

it('keeps a field of a record from taking a file the project does not show', function () {
    Schema::create('articles', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('cover_id')->nullable();
        $table->unsignedBigInteger('icon_id')->nullable();
        $table->text('body')->nullable();
        $table->json('gallery')->nullable();
        $table->softDeletes();
    });

    $theirs = fileOf('viter', 'chuzha');
    $article = Article::query()->create();

    onlyTheFilesOf('zoria');

    livewire(ArticleForm::class, ['article' => $article])
        ->call('callSchemaComponentMethod', 'form.cover_id', 'chosen', ['id' => $theirs->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($article->refresh()->cover_id)->toBeNull();
});

it('writes a new file into the library whatever the project shows of it', function () {
    onlyTheFilesOf('zoria');

    // The narrowing is a way of showing the library, and a row on its way into
    // the table is not being shown to anybody.
    livewire(MediaLibrary::class)
        ->set('files', [UploadedFile::fake()->image('nova.png', 40, 30)])
        ->assertHasNoErrors();

    expect(Media::query()->count())->toBe(1);
});

it('draws the picture of a file the wall of this person would not show', function () {
    config()->set('mediator.thumbnails.cache', storage_path('framework/testing/thumbnails'));

    $canvas = imagecreatetruecolor(60, 40);
    imagefilledrectangle($canvas, 0, 0, 59, 39, imagecolorallocate($canvas, 10, 120, 90));

    ob_start();
    imagepng($canvas);
    $bytes = (string) ob_get_clean();
    imagedestroy($canvas);

    $theirs = fileOf('viter', 'chuzha');
    $theirs->update(['path' => 'media/2026/08/chuzha.png', 'ext' => 'png', 'type' => 'image/png']);

    Storage::disk('public')->put($theirs->path, $bytes);

    $address = (string) $theirs->thumbnailUrl;

    onlyTheFilesOf('zoria');

    // A picture is served without a session and to nobody in particular, so the
    // address stands or falls on its signature. A narrowing written around
    // whoever is signed in would answer every one of them with nothing.
    $this->get($address)->assertOk();

    File::deleteDirectory(storage_path('framework/testing/thumbnails'));
});
