<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mediator\Filament\Forms\MediaImagePlugin;
use Mediator\Models\Media;
use Mediator\Tests\Fixtures\Article;
use Mediator\Tests\Fixtures\ArticleForm;
use Mediator\Tests\Fixtures\Person;

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
 * A file in the library, of the kind the test needs it to be.
 */
function textFile(
    string $name = 'sud',
    string $type = 'image/webp',
    string $ext = 'webp',
    ?string $alt = null,
    ?int $width = 1200,
    ?int $height = 800,
): Media {
    /** @var Media */
    return Media::query()->create([
        'disk' => 'public',
        'directory' => 'media/2026/08',
        'visibility' => 'public',
        'name' => $name,
        'path' => 'media/2026/08/'.$name.'.'.$ext,
        'width' => $width,
        'height' => $height,
        'size' => 204800,
        'type' => $type,
        'ext' => $ext,
        'alt' => $alt,
    ]);
}

/**
 * The markup the editor was told to put into the text.
 *
 * @param  array<string, mixed>  $params
 */
function insertedMarkup(array $params): string
{
    $commands = $params['commands'] ?? [];

    return implode('', array_map(
        fn (array $command): string => implode('', $command['arguments'] ?? []),
        is_array($commands) ? $commands : [],
    ));
}

it('stands in the toolbar of the editor as a tool of its own', function () {
    livewire(ArticleForm::class)
        ->assertFormFieldExists('body', fn ($field): bool => array_key_exists('image', $field->getTools()))
        ->assertFormFieldExists('body', fn ($field): bool => in_array('image', array_merge(...$field->getToolbarButtons()), true));
});

it('opens the library for pictures alone, whatever else it holds', function () {
    expect(MediaImagePlugin::pictures())
        ->toContain('image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml')
        ->not->toContain('application/pdf', 'video/mp4');
});

it('puts the chosen pictures into the text with their address, description and size', function () {
    $picture = textFile(alt: 'Зала засідань');

    livewire(ArticleForm::class)
        ->callFormComponentAction('body', 'image', arguments: ['ids' => [$picture->id]])
        ->assertDispatched(
            'run-rich-editor-commands',
            fn (string $event, array $params): bool => insertedMarkup($params)
                === '<img src="'.$picture->url.'" alt="Зала засідань" width="1200" height="800">',
        );
});

it('puts several pictures in at once, in the order they were chosen', function () {
    $first = textFile('persha');
    $second = textFile('druha');

    livewire(ArticleForm::class)
        ->callFormComponentAction('body', 'image', arguments: ['ids' => [$second->id, $first->id]])
        ->assertDispatched(
            'run-rich-editor-commands',
            fn (string $event, array $params): bool => insertedMarkup($params)
                === '<img src="'.$second->url.'" alt="" width="1200" height="800">'
                    .'<img src="'.$first->url.'" alt="" width="1200" height="800">',
        );
});

it('leaves the size out of a drawing, which the library never measured', function () {
    $drawing = textFile('znak', type: 'image/svg+xml', ext: 'svg', width: null, height: null);

    livewire(ArticleForm::class)
        ->callFormComponentAction('body', 'image', arguments: ['ids' => [$drawing->id]])
        ->assertDispatched(
            'run-rich-editor-commands',
            fn (string $event, array $params): bool => insertedMarkup($params) === '<img src="'.$drawing->url.'" alt="">',
        );
});

it('puts nothing into the text for a file that is not a picture', function () {
    $paper = textFile('dohovir', type: 'application/pdf', ext: 'pdf', width: null, height: null);

    livewire(ArticleForm::class)
        ->callFormComponentAction('body', 'image', arguments: ['ids' => [$paper->id]])
        ->assertNotDispatched('run-rich-editor-commands');
});

it('keeps the tag of a picture in the text the editor saves', function () {
    $picture = textFile(alt: 'Зала засідань');
    $article = Article::query()->create();
    $tag = '<img src="'.$picture->url.'" alt="Зала засідань" width="1200" height="800">';

    livewire(ArticleForm::class, ['article' => $article])
        ->fillForm(['body' => '<p>Текст</p>'.$tag])
        ->call('save')
        ->assertHasNoErrors();

    expect((string) $article->refresh()->body)
        ->toContain('src="'.$picture->url.'"')
        ->toContain('alt="Зала засідань"')
        ->toContain('width="1200"')
        ->toContain('height="800"')
        // The size stands in the attributes of the tag and nowhere else: how
        // wide the picture is drawn is laid down by the site.
        ->not->toContain('style=');
});

it('writes the size of a picture that arrived in a style back into the attributes', function () {
    $article = Article::query()->create();

    livewire(ArticleForm::class, ['article' => $article])
        ->fillForm(['body' => '<img src="/storage/media/2026/08/sud.webp" style="width: 640px; height: 480px;">'])
        ->call('save')
        ->assertHasNoErrors();

    expect((string) $article->refresh()->body)
        ->toContain('width="640"')
        ->toContain('height="480"')
        ->not->toContain('style=');
});
