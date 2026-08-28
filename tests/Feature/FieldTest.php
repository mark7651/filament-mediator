<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mediator\Filament\Forms\MediaField;
use Mediator\Models\Media;
use Mediator\Tests\Fixtures\Article;
use Mediator\Tests\Fixtures\ArticleForm;
use Mediator\Tests\Fixtures\Person;
use Mediator\Uploads\Upload;

beforeEach(function () {
    Schema::create('articles', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('cover_id')->nullable();
        $table->unsignedBigInteger('icon_id')->nullable();
        $table->text('body')->nullable();
        $table->softDeletes();
    });

    $this->actingAs(new Person);
});

/**
 * A file in the library, of whatever kind the field under test asks for.
 */
function fieldFile(string $name = 'obkladynka', string $type = 'image/jpeg', string $ext = 'jpg'): Media
{
    /** @var Media */
    return Media::query()->create([
        'disk' => 'public',
        'directory' => 'media/2026/08',
        'visibility' => 'public',
        'name' => $name,
        'path' => 'media/2026/08/'.$name.'.'.$ext,
        'width' => 1200,
        'height' => 800,
        'size' => 204800,
        'type' => $type,
        'ext' => $ext,
    ]);
}

it('takes every kind the library holds where the field was not narrowed', function () {
    livewire(ArticleForm::class)
        ->assertFormFieldExists('cover_id', fn (MediaField $field): bool => $field->getTakes() === Upload::takes())
        ->assertFormFieldExists('icon_id', fn (MediaField $field): bool => $field->getTakes() === ['image/svg+xml', 'image/png']);
});

it('writes the file the library handed over into the record', function () {
    $article = Article::query()->create();
    $cover = fieldFile();

    livewire(ArticleForm::class, ['article' => $article])
        ->call('callSchemaComponentMethod', 'form.cover_id', 'chosen', ['id' => $cover->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($article->refresh()->cover_id)->toBe($cover->id);
});

it('refuses a file of a kind it does not take, whoever asks for it', function () {
    $article = Article::query()->create();
    $photograph = fieldFile('foto');

    livewire(ArticleForm::class, ['article' => $article])
        ->call('callSchemaComponentMethod', 'form.icon_id', 'chosen', ['id' => $photograph->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($article->refresh()->icon_id)->toBeNull();
});

it('leaves the record without a file when the editor takes the file off', function () {
    $cover = fieldFile();
    $article = Article::query()->create(['cover_id' => $cover->id]);

    livewire(ArticleForm::class, ['article' => $article])
        ->callFormComponentAction('cover_id', 'clear')
        ->call('save')
        ->assertHasNoErrors();

    expect($article->refresh()->cover_id)->toBeNull();
});

it('shows the file standing in the field with its name and its size', function () {
    $cover = fieldFile('vyvicka');

    livewire(ArticleForm::class, ['article' => Article::query()->create(['cover_id' => $cover->id])])
        ->assertSee('vyvicka')
        ->assertSee('1200')
        ->assertSee(__('mediator::media.field.change'));
});

it('opens the library for the kinds the field takes and no others', function () {
    fieldFile('znak', type: 'image/svg+xml', ext: 'svg');
    fieldFile('foto');

    $form = livewire(ArticleForm::class)->mountFormComponentAction('icon_id', 'choose');

    $library = (string) $form->instance()->getMountedAction()?->getModalContent()?->render();

    expect($library)->toContain('znak')->not->toContain('foto');
});
