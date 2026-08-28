<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mediator\Livewire\MediaLibrary;
use Mediator\Models\Media;
use Mediator\Tests\Fixtures\Article;
use Mediator\Tests\Fixtures\Person;
use Mediator\Uses\Places;

beforeEach(function () {
    Schema::create('articles', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('cover_id')->nullable();
        $table->unsignedBigInteger('icon_id')->nullable();
        $table->string('title')->nullable();
        $table->string('body')->default('');
        $table->softDeletes();
    });
});

function oneFile(): Media
{
    return Media::query()->create([
        'disk' => 'public',
        'directory' => 'media/2026/08',
        'visibility' => 'public',
        'name' => 'a-picture',
        'path' => 'media/2026/08/a-picture.webp',
        'type' => 'image/webp',
        'ext' => 'webp',
    ]);
}

it('counts the records a file stands in', function () {
    $file = oneFile();

    Article::query()->create(['cover_id' => $file->id]);
    Article::query()->create(['cover_id' => $file->id]);
    Article::query()->create(['icon_id' => $file->id]);
    Article::query()->create(['cover_id' => null]);

    app(Places::class)
        ->standsIn(Article::class, 'cover_id')
        ->standsIn(Article::class, 'icon_id');

    expect($file->usedBy())->toBe(3);
});

it('counts a record that was thrown away only where it is registered as counting', function () {
    $file = oneFile();

    Article::query()->create(['cover_id' => $file->id])->delete();

    expect((new Places)->standsIn(Article::class, 'cover_id')->standing($file))->toBe(0)
        ->and((new Places)->standsIn(Article::class, 'cover_id', withTrashed: true)->standing($file))->toBe(1);
});

it('counts a place of its own the way the project counts it', function () {
    $file = oneFile();

    Article::query()->create(['body' => 'a picture number '.$file->id.' stands here']);

    app(Places::class)->counted(fn (Media $one): int => Article::query()
        ->where('body', 'like', '%number '.$one->getKey().' %')
        ->count());

    expect($file->usedBy())->toBe(1);
});

it('counts nothing where the project wrote nothing down', function () {
    expect(oneFile()->usedBy())->toBe(0);
});

it('names the records a file stands in', function () {
    $file = oneFile();

    Article::query()->create(['cover_id' => $file->id, 'title' => 'Сімейне право']);

    app(Places::class)->standsIn(Article::class, 'cover_id');

    expect($file->standsIn())->toBe([
        ['kind' => 'Article', 'label' => 'Сімейне право', 'url' => null],
    ]);
});

it('names a record carrying no name of its own by the number of it', function () {
    $file = oneFile();

    $article = Article::query()->create(['cover_id' => $file->id]);

    app(Places::class)->standsIn(Article::class, 'cover_id');

    expect($file->standsIn())->toBe([
        ['kind' => 'Article', 'label' => '#'.$article->id, 'url' => null],
    ]);
});

it('names a place of its own only where the project hands over the records as well', function () {
    $file = oneFile();

    Article::query()->create(['title' => 'Стаття з картинкою', 'body' => 'a picture number '.$file->id.' stands here']);

    $standing = fn (Media $one) => Article::query()->where('body', 'like', '%number '.$one->getKey().' %');

    expect((new Places)->counted(fn (Media $one): int => $standing($one)->count())->named($file))->toBe([])
        ->and((new Places)
            ->counted(fn (Media $one): int => $standing($one)->count(), fn (Media $one) => $standing($one)->get())
            ->named($file))
        ->toBe([['kind' => 'Article', 'label' => 'Стаття з картинкою', 'url' => null]]);
});

it('says in the panel of details what the file stands in and how much of it it cannot name', function () {
    $this->actingAs(new Person);

    $file = oneFile();

    Article::query()->create(['cover_id' => $file->id, 'title' => 'Сімейне право']);
    Article::query()->create(['body' => 'a picture number '.$file->id.' stands here']);

    app(Places::class)
        ->standsIn(Article::class, 'cover_id')
        ->counted(fn (Media $one): int => Article::query()
            ->where('body', 'like', '%number '.$one->getKey().' %')
            ->count());

    livewire(MediaLibrary::class)
        ->call('open', $file->id)
        ->assertSee('Сімейне право')
        ->assertSee(trans_choice('mediator::media.elsewhere', 1, ['count' => 1]));
});

it('says which files of the library stand somewhere, asked of the places and not of the files', function () {
    $standing = oneFile();
    $nobodys = oneFile();

    Article::query()->create(['cover_id' => $standing->id]);
    Article::query()->create(['icon_id' => $standing->id]);
    Article::query()->create(['cover_id' => null]);

    $places = (new Places)
        ->standsIn(Article::class, 'cover_id')
        ->standsIn(Article::class, 'icon_id');

    expect($places->standingAnywhere())->toBe([$standing->id])
        ->and($places->standingAnywhere())->not->toContain($nobodys->id);
});

it('holds the file of a thrown away record only where the place counts the thrown away', function () {
    $file = oneFile();

    Article::query()->create(['cover_id' => $file->id])->delete();

    expect((new Places)->standsIn(Article::class, 'cover_id')->standingAnywhere())->toBe([])
        ->and((new Places)->standsIn(Article::class, 'cover_id', withTrashed: true)->standingAnywhere())->toBe([$file->id]);
});

it('says nothing of a place of its own until the project hands over the files standing in it', function () {
    $file = oneFile();

    Article::query()->create(['body' => 'a picture number '.$file->id.' stands here']);

    $counting = fn (Media $one): int => Article::query()
        ->where('body', 'like', '%number '.$one->getKey().' %')
        ->count();

    expect((new Places)->counted($counting)->standingAnywhere())->toBe([])
        ->and((new Places)->counted($counting, anywhere: fn (): array => [$file->id])->standingAnywhere())
        ->toBe([$file->id]);
});
