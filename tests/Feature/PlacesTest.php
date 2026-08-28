<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mediator\Models\Media;
use Mediator\Tests\Fixtures\Article;
use Mediator\Uses\Places;

beforeEach(function () {
    Schema::create('articles', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('cover_id')->nullable();
        $table->unsignedBigInteger('icon_id')->nullable();
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
