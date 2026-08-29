<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mediator\Livewire\MediaLibrary;
use Mediator\Models\Media;
use Mediator\Tests\Fixtures\Article;
use Mediator\Tests\Fixtures\Person;
use Mediator\Tests\Fixtures\Telling;
use Mediator\Uses\Places;
use Mediator\Uses\Texts;

beforeEach(function () {
    Schema::create('articles', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('cover_id')->nullable();
        $table->unsignedBigInteger('icon_id')->nullable();
        $table->string('title')->nullable();
        $table->string('body')->default('');
        $table->softDeletes();
    });

    Schema::create('tellings', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('article_id');
        $table->string('locale')->default('uk');
        $table->text('body')->nullable();
    });
});

/**
 * A file of the library, named the way the library names files.
 */
function fileInText(string $name = 'a-picture'): Media
{
    /** @var Media */
    return Media::query()->create([
        'disk' => 'public',
        'directory' => 'media/2026/08',
        'visibility' => 'public',
        'name' => $name,
        'path' => 'media/2026/08/'.$name.'.webp',
        'type' => 'image/webp',
        'ext' => 'webp',
    ]);
}

function textHolding(Media $file): string
{
    return '<p>Слово</p><img src="http://localhost/storage/'.$file->path.'" alt="" width="800" height="600">';
}

it('writes down the file standing in a text as the record is saved', function () {
    $file = fileInText();

    app(Places::class)->standsInText(Article::class, 'body');

    Article::query()->create(['title' => 'Про податки', 'body' => textHolding($file)]);

    expect($file->usedBy())->toBe(1)
        ->and($file->standsIn())->toBe([
            ['kind' => 'Article', 'label' => 'Про податки', 'url' => null],
        ]);
});

it('counts a file put into a text as a link the way it counts one put in as a picture', function () {
    $file = fileInText('dohovir');

    app(Places::class)->standsInText(Article::class, 'body');

    Article::query()->create(['body' => '<a href="/storage/media/2026/08/dohovir.webp">Договір</a>']);

    expect($file->usedBy())->toBe(1);
});

it('lets go of the file when it is taken out of the text and the record saved', function () {
    $file = fileInText();

    app(Places::class)->standsInText(Article::class, 'body');

    $article = Article::query()->create(['body' => textHolding($file)]);

    expect($file->usedBy())->toBe(1);

    $article->update(['body' => '<p>Саме слово</p>']);

    // Asked of the record anew: a file keeps the number of its places for as
    // long as it is held, so that drawing one panel counts them once.
    expect($file->fresh()?->usedBy())->toBe(0)
        ->and(Texts::query()->count())->toBe(0);
});

it('says nothing of a text holding an address of another site', function () {
    $file = fileInText();

    app(Places::class)->standsInText(Article::class, 'body');

    Article::query()->create(['body' => '<img src="https://example.com/photo/inshyi-fail.jpg">']);

    expect($file->usedBy())->toBe(0);
});

it('holds the text of a record waiting in the trash and lets go of one gone for good', function () {
    $file = fileInText();

    app(Places::class)->standsInText(Article::class, 'body');

    $article = Article::query()->create(['body' => textHolding($file)]);

    $article->delete();

    expect($file->fresh()?->usedBy())->toBe(1);

    $article->forceDelete();

    expect($file->fresh()?->usedBy())->toBe(0);
});

it('counts a record told in two languages as the one record it is', function () {
    $file = fileInText();

    app(Places::class)->standsInText(
        Telling::class,
        'body',
        stands: fn (Telling $telling): Article => $telling->article,
    );

    $article = Article::query()->create(['title' => 'Про податки']);

    foreach (['uk', 'en'] as $locale) {
        Telling::query()->create(['article_id' => $article->id, 'locale' => $locale, 'body' => textHolding($file)]);
    }

    expect($file->usedBy())->toBe(1)
        ->and($file->standsIn())->toBe([
            ['kind' => 'Article', 'label' => 'Про податки', 'url' => null],
        ]);
});

it('keeps a file standing only in a text out of the wall of the ownerless ones', function () {
    $this->actingAs(new Person);

    $standing = fileInText('u-teksti');
    $nobodys = fileInText('nichyia');

    app(Places::class)->standsInText(Article::class, 'body');

    Article::query()->create(['body' => textHolding($standing)]);

    livewire(MediaLibrary::class)
        ->set('unused', true)
        ->assertSee($nobodys->name)
        ->assertDontSee($standing->name);
});

it('reads the texts anew where they were changed past the model', function () {
    $file = fileInText();

    app(Places::class)->standsInText(Article::class, 'body');

    $article = Article::query()->create(['body' => textHolding($file)]);

    // An import writing straight to the table, which the saving of a record
    // never hears about.
    Article::query()->whereKey($article->id)->toBase()->update(['body' => '<p>Саме слово</p>']);

    expect($file->usedBy())->toBe(1);

    $this->artisan('mediator:relink')->assertSuccessful();

    expect($file->fresh()?->usedBy())->toBe(0);
});

it('says so instead of doing nothing quietly where no text is written down', function () {
    $this->artisan('mediator:relink')
        ->expectsOutputToContain('No text of this project is written down')
        ->assertSuccessful();
});

it('lets go of the holdings of a file that is deleted', function () {
    $file = fileInText();

    app(Places::class)->standsInText(Article::class, 'body');

    Article::query()->create(['body' => textHolding($file)]);

    expect(Texts::query()->count())->toBe(1);

    $file->delete();

    expect(Texts::query()->count())->toBe(0);
});
