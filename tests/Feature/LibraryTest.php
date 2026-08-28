<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Mediator\Filament\Pages\Library;
use Mediator\Livewire\MediaLibrary;
use Mediator\Models\Media;
use Mediator\Tests\Fixtures\ClosedPolicy;
use Mediator\Tests\Fixtures\Person;
use Mediator\Uses\Places;

beforeEach(function () {
    Storage::fake('public');

    $this->actingAs(new Person);
});

afterEach(function () {
    File::deleteDirectory(app()->langPath('vendor/mediator'));
});

function libraryFile(
    string $name = 'obkladynka',
    ?string $title = null,
    string $type = 'image/jpeg',
    string $ext = 'jpg',
    ?string $alt = null,
): Media {
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
        'title' => $title,
        'alt' => $alt,
    ]);
}

/**
 * A png of nothing but see-through pixels, which is what a mark of a practice
 * drawn for a light page and a dark one both looks like.
 */
function seeThroughPng(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('znak.png', (string) ImageManager::gd()->create(20, 20)->toPng());
}

it('shows the library as a wall of every file in it', function () {
    $files = collect(range(1, 3))->map(fn (int $number): Media => libraryFile('kartynka-'.$number));

    $wall = livewire(MediaLibrary::class);

    foreach ($files as $file) {
        $wall->assertSee($file->name);
    }
});

it('holds back the rest of the library until the wall is asked for more', function () {
    collect(range(1, 30))->each(fn (int $number): Media => libraryFile('kartynka-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT)));

    livewire(MediaLibrary::class)
        ->assertSee('kartynka-30')
        ->assertDontSee('kartynka-01')
        ->call('loadMore')
        ->assertSee('kartynka-01');
});

it('holds the place of every card still to come while the wall is asked for more', function () {
    collect(range(1, 30))->each(fn (int $number): Media => libraryFile('kartynka-'.$number));

    $wall = livewire(MediaLibrary::class);

    expect(substr_count($wall->html(), 'class="media-ghost"'))->toBe(6);

    $wall->call('loadMore')->assertDontSeeHtml('class="media-ghost"');
});

it('opens a card from the keyboard as well as from the pointer', function () {
    libraryFile('obkladynka');

    livewire(MediaLibrary::class)
        ->assertSeeHtml('x-on:keydown.enter.self')
        ->assertSeeHtml('x-on:keydown.space.self.prevent');
});

it('finds a file by the name it was given rather than by the name of the file', function () {
    $named = libraryFile('dsc-4210', title: 'Портрет Олени');
    $described = libraryFile('dsc-4211', alt: 'Юрист біля мікрофона');
    $other = libraryFile('dsc-4212');

    livewire(MediaLibrary::class)
        ->set('search', 'Портрет')
        ->assertSee($named->name)
        ->assertDontSee($other->name)
        ->set('search', 'мікрофона')
        ->assertSee($described->name)
        ->assertDontSee($other->name);
});

it('leaves in the wall only the files of the type it was asked for', function () {
    $picture = libraryFile('obkladynka');
    $document = libraryFile('dohovir', type: 'application/pdf', ext: 'pdf');
    $recording = libraryFile('vebinar', type: 'video/mp4', ext: 'mp4');

    livewire(MediaLibrary::class)
        ->set('type', 'document')
        ->assertSee($document->name)
        ->assertDontSee($picture->name)
        ->assertDontSee($recording->name)
        ->set('type', 'video')
        ->assertSee($recording->name)
        ->assertDontSee($document->name);
});

it('shows the whole library again when the filter is taken off', function () {
    $picture = libraryFile('obkladynka');
    $document = libraryFile('dohovir', type: 'application/pdf', ext: 'pdf');

    livewire(MediaLibrary::class)
        ->set('type', 'document')
        ->assertDontSee($picture->name)
        ->set('type', '')
        ->assertSee($picture->name)
        ->assertSee($document->name);
});

it('tells everything the library knows about the file that was opened', function () {
    $picture = libraryFile('obkladynka', title: 'Обкладинка вебінару', alt: 'Юрист біля мікрофона');

    livewire(MediaLibrary::class)
        ->call('open', $picture->id)
        ->assertSet('title', 'Обкладинка вебінару')
        ->assertSet('alt', 'Юрист біля мікрофона')
        ->assertSee('1200')
        ->assertSee('800');
});

it('writes a new name and a new alt without touching the file itself', function () {
    $picture = libraryFile();

    livewire(MediaLibrary::class)
        ->call('open', $picture->id)
        ->set('title', 'Обкладинка вебінару')
        ->set('alt', 'Юрист біля мікрофона')
        ->call('saveDetails');

    $picture->refresh();

    expect($picture->title)->toBe('Обкладинка вебінару')
        ->and($picture->alt)->toBe('Юрист біля мікрофона')
        ->and($picture->name)->toBe('obkladynka')
        ->and($picture->path)->toBe('media/2026/08/obkladynka.jpg');
});

it('walks to the file beside the one that is open', function () {
    $first = libraryFile('kartynka-1');
    $second = libraryFile('kartynka-2');
    $third = libraryFile('kartynka-3');

    livewire(MediaLibrary::class)
        ->call('open', $second->id)
        // The wall stands with the newest first, so the next card is the older
        // file and the previous one is the newer.
        ->call('next')
        ->assertSet('openId', $first->id)
        ->call('previous')
        ->assertSet('openId', $second->id)
        ->call('previous')
        ->assertSet('openId', $third->id);
});

it('takes the file off the disk with the record when a card is deleted', function () {
    $picture = libraryFile();
    Storage::disk('public')->put($picture->path, 'file');

    livewire(MediaLibrary::class)->call('delete', $picture->id);

    expect(Media::query()->count())->toBe(0);

    Storage::disk('public')->assertMissing($picture->path);
});

it('clears out the files that were ticked, one deletion each', function () {
    $first = libraryFile('kartynka-1');
    $second = libraryFile('kartynka-2');
    $kept = libraryFile('kartynka-3');

    foreach ([$first, $second, $kept] as $file) {
        Storage::disk('public')->put($file->path, 'file');
    }

    livewire(MediaLibrary::class)
        ->set('chosen', [$first->id, $second->id])
        ->call('deleteChosen');

    expect(Media::query()->pluck('id')->all())->toBe([$kept->id]);

    Storage::disk('public')->assertMissing($first->path);
    Storage::disk('public')->assertExists($kept->path);
});

it('says how many records a file stands in before it is deleted', function () {
    $picture = libraryFile();

    app(Places::class)->counted(fn (Media $file): int => 2);

    livewire(MediaLibrary::class)
        ->call('open', $picture->id)
        ->assertSee(trans_choice('mediator::media.delete.in_use', 2, ['count' => 2]));
});

it('says only that the file leaves the disk where the project counts nothing', function () {
    $picture = libraryFile();

    livewire(MediaLibrary::class)
        ->call('open', $picture->id)
        ->assertSee(__('mediator::media.delete.unused'))
        ->assertSee(trans_choice('mediator::media.standing', 0, ['count' => 0]));
});

it('keeps the library out of the hands the policy of the project keeps it from', function () {
    $picture = libraryFile();

    Gate::policy(Media::class, ClosedPolicy::class);

    livewire(MediaLibrary::class)
        ->set('chosen', [$picture->id])
        ->assertDontSee(__('mediator::media.actions.delete_selected'))
        ->call('delete', $picture->id)
        ->assertForbidden();

    expect(Media::query()->count())->toBe(1);
});

it('offers the whole library to everyone where the project says nothing', function () {
    $picture = libraryFile();

    livewire(MediaLibrary::class)
        ->set('chosen', [$picture->id])
        ->assertSee(__('mediator::media.actions.delete_selected'));
});

it('stands in the panel as a section of its own, styled by the package', function () {
    $this->get(Library::getUrl())
        ->assertOk()
        ->assertSee(__('mediator::media.plural_title'))
        ->assertSee(__('mediator::media.search'))
        ->assertSee('.media-card__frame', escape: false);
});

it('speaks the language the application speaks', function () {
    app()->setLocale('uk');

    livewire(MediaLibrary::class)->assertSee('Пошук за назвою або описом');

    app()->setLocale('en');

    livewire(MediaLibrary::class)->assertSee('Search by name or description');
});

it('lets the project put a word of its own in place of one of the package', function () {
    $published = app()->langPath('vendor/mediator/uk');

    File::ensureDirectoryExists($published);
    File::put($published.'/media.php', "<?php\n\nreturn ['search' => 'Шукати файл'];\n");

    app()->setLocale('uk');

    livewire(MediaLibrary::class)
        ->assertSee('Шукати файл')
        // Everything the project said nothing about still comes from the
        // package rather than disappearing with the file that was published.
        ->assertSee(__('mediator::media.types.all'));
});

it('forgets what was ticked when the wall is filtered anew', function () {
    $picture = libraryFile('obkladynka');

    livewire(MediaLibrary::class)
        ->set('chosen', [$picture->id])
        ->set('type', 'document')
        ->assertSet('chosen', [])
        ->call('deleteChosen');

    expect(Media::query()->count())->toBe(1);
});

it('refuses a file of a kind the library does not take', function () {
    livewire(MediaLibrary::class)
        ->set('files', [UploadedFile::fake()->createWithContent('payload.html', '<script>alert(1)</script>')])
        ->assertHasErrors('files.0');

    expect(Media::query()->count())->toBe(0);
});

it('names the file on disk after what it is, not after what it was called', function () {
    livewire(MediaLibrary::class)
        ->set('files', [UploadedFile::fake()->create('dohovir.html', 10, 'application/pdf')])
        ->assertHasNoErrors();

    $document = Media::query()->sole();

    expect($document->ext)->toBe('pdf')
        ->and($document->path)->toEndWith('.pdf')
        ->and($document->title)->toBe('dohovir');
});

it('keeps a see-through png see-through', function () {
    livewire(MediaLibrary::class)->set('files', [seeThroughPng()]);

    $mark = Media::query()->sole();
    $stored = ImageManager::gd()->read(Storage::disk('public')->get($mark->path));

    expect($mark->type)->toBe('image/webp')
        ->and($stored->pickColor(10, 10)->isTransparent())->toBeTrue();
});

it('writes a film down as it arrived, byte for byte', function () {
    livewire(MediaLibrary::class)
        ->set('files', [UploadedFile::fake()->createWithContent('vebinar.mp4', 'moving pictures')->mimeType('video/mp4')])
        ->assertHasNoErrors();

    $film = Media::query()->sole();

    expect($film->type)->toBe('video/mp4')
        ->and($film->ext)->toBe('mp4')
        ->and(Storage::disk('public')->get($film->path))->toBe('moving pictures');
});

it('leaves a gif and a webp alone rather than encoding them again', function () {
    $animation = UploadedFile::fake()->image('animatsiia.gif', 20, 10);
    $mark = UploadedFile::fake()->image('znak.webp', 20, 10);

    $asDropped = [
        'animatsiia' => (string) file_get_contents($animation->getRealPath()),
        'znak' => (string) file_get_contents($mark->getRealPath()),
    ];

    livewire(MediaLibrary::class)->set('files', [$animation, $mark]);

    foreach ($asDropped as $title => $bytes) {
        $file = Media::query()->where('title', $title)->sole();

        expect(Storage::disk('public')->get($file->path))->toBe($bytes);
    }
});

it('refuses a picture heavier than the library takes and says so where the file was dropped', function () {
    livewire(MediaLibrary::class)
        ->set('files', [UploadedFile::fake()->create('velyka.jpg', 10241, 'image/jpeg')])
        ->assertHasErrors('files.0')
        ->assertSee(__('mediator::media.refused.weight', ['name' => 'velyka.jpg', 'limit' => 10]));

    expect(Media::query()->count())->toBe(0);
});

it('takes a film of a hundred megabytes and refuses the one past it', function () {
    livewire(MediaLibrary::class)
        ->set('files', [UploadedFile::fake()->create('zavelykyi.mp4', 102401, 'video/mp4')])
        ->assertHasErrors('files.0');

    expect(Media::query()->count())->toBe(0);

    livewire(MediaLibrary::class)
        ->set('files', [UploadedFile::fake()->create('vebinar.mp4', 102400, 'video/mp4')])
        ->assertHasNoErrors();

    expect(Media::query()->count())->toBe(1);
});

it('says so instead of falling over when a file calls itself a picture and holds nothing readable', function () {
    livewire(MediaLibrary::class)
        ->set('files', [UploadedFile::fake()->createWithContent('zlamana.jpg', 'not a picture at all')])
        ->assertHasNoErrors()
        ->assertNotified(__('mediator::media.refused.broken', ['name' => 'zlamana.jpg']));

    expect(Media::query()->count())->toBe(0);
});

it('offers to put a new picture behind the open file', function () {
    $picture = libraryFile();

    livewire(MediaLibrary::class)
        ->call('open', $picture->id)
        ->assertSee(__('mediator::media.actions.replace'));
});

it('keeps the record and the words of the editor when the picture behind them changes', function () {
    $picture = libraryFile(title: 'Обкладинка вебінару', alt: 'Юрист біля мікрофона');
    Storage::disk('public')->put($picture->path, 'the old picture');

    livewire(MediaLibrary::class)
        ->call('open', $picture->id)
        ->set('replacement', UploadedFile::fake()->image('nova.jpg', 4000, 2000));

    $was = $picture->path;
    $picture->refresh();

    expect($picture->title)->toBe('Обкладинка вебінару')
        ->and($picture->alt)->toBe('Юрист біля мікрофона')
        ->and($picture->type)->toBe('image/webp')
        ->and($picture->width)->toBe(2560)
        ->and($picture->path)->not->toBe($was);

    Storage::disk('public')->assertMissing($was);
    Storage::disk('public')->assertExists($picture->path);
});

it('narrows the wall to the kinds the field that opened the library takes', function () {
    libraryFile('znak', type: 'image/svg+xml', ext: 'svg');
    libraryFile('foto', type: 'image/jpeg', ext: 'jpg');

    livewire(MediaLibrary::class, ['picking' => true, 'takes' => ['image/svg+xml']])
        ->assertSee('znak')
        ->assertDontSee('foto');
});

it('takes a card of the wall for the file itself while a field waits for one', function () {
    $picture = libraryFile();

    livewire(MediaLibrary::class, ['picking' => true])
        ->assertSeeHtml('wire:click="choose('.$picture->id.')"');
});

it('takes a card of the wall for a tick while several files are being gathered', function () {
    $picture = libraryFile();

    livewire(MediaLibrary::class, ['picking' => true, 'many' => true])
        ->assertSeeHtml('wire:click="toggle('.$picture->id.')"');
});

it('takes a card of the wall for the details of the file on the page of the library', function () {
    $picture = libraryFile();

    livewire(MediaLibrary::class)
        ->assertSeeHtml('wire:click="open('.$picture->id.')"');
});

it('hands the file the editor opened back to the field the library was opened for', function () {
    $picture = libraryFile();

    livewire(MediaLibrary::class, ['picking' => true])
        ->call('choose', $picture->id)
        ->assertDispatched('media-chosen', id: $picture->id);
});

it('hands over a file uploaded while choosing without asking for it to be chosen again', function () {
    $wall = livewire(MediaLibrary::class, ['picking' => true])->set('files', [seeThroughPng()]);

    $wall->assertDispatched('media-chosen', id: (int) Media::query()->sole()->id);
});

it('says nothing about a file uploaded on the page of the library, which nobody is waiting for', function () {
    livewire(MediaLibrary::class)
        ->set('files', [seeThroughPng()])
        ->assertNotDispatched('media-chosen');
});

it('refuses a file of a kind the field does not take, whatever else the library holds', function () {
    livewire(MediaLibrary::class, ['picking' => true, 'takes' => ['image/svg+xml']])
        ->set('files', [seeThroughPng()])
        ->assertHasErrors('files.0');

    expect(Media::query()->count())->toBe(0);
});

it('hands over every ticked file at once, in the order they were ticked', function () {
    $first = libraryFile('persha');
    $second = libraryFile('druha');

    livewire(MediaLibrary::class, ['picking' => true, 'many' => true])
        ->call('toggle', $second->id)
        ->call('toggle', $first->id)
        ->call('chooseMany')
        ->assertDispatched('media-chosen', ids: [(int) $second->id, (int) $first->id]);
});

it('says nothing where nothing was ticked', function () {
    livewire(MediaLibrary::class, ['picking' => true, 'many' => true])
        ->call('chooseMany')
        ->assertNotDispatched('media-chosen');
});

it('keeps the ticked files while the editor looks for the next one', function () {
    $picture = libraryFile('persha');

    livewire(MediaLibrary::class, ['picking' => true, 'many' => true])
        ->call('toggle', $picture->id)
        ->set('search', 'druha')
        ->assertSet('chosen', [(int) $picture->id]);
});

it('forgets the ticked files when the wall of the library itself is searched', function () {
    $picture = libraryFile('persha');

    livewire(MediaLibrary::class)
        ->call('toggle', $picture->id)
        ->set('search', 'druha')
        ->assertSet('chosen', []);
});

it('ticks a file uploaded while several are being gathered rather than handing it over alone', function () {
    $wall = livewire(MediaLibrary::class, ['picking' => true, 'many' => true])
        ->set('files', [seeThroughPng()]);

    $wall
        ->assertNotDispatched('media-chosen')
        ->assertSet('chosen', [(int) Media::query()->sole()->id]);
});

it('leaves in the wall only the files nobody stands on', function () {
    $standing = libraryFile('obkladynka');
    $nobodys = libraryFile('zabuta');

    app(Places::class)->counted(
        fn (Media $file): int => (int) ($file->id === $standing->id),
        anywhere: fn (): array => [$standing->id],
    );

    livewire(MediaLibrary::class)
        ->set('unused', true)
        ->assertSee($nobodys->name)
        ->assertDontSee($standing->name)
        ->set('unused', false)
        ->assertSee($standing->name)
        ->assertSee($nobodys->name);
});

it('keeps the wall on the files nobody stands on while the search and the type of it change', function () {
    $standing = libraryFile('dohovir', type: 'application/pdf', ext: 'pdf');
    $nobodys = libraryFile('zvit', type: 'application/pdf', ext: 'pdf');
    $picture = libraryFile('obkladynka');

    app(Places::class)->counted(
        fn (Media $file): int => (int) ($file->id === $standing->id),
        anywhere: fn (): array => [$standing->id],
    );

    livewire(MediaLibrary::class)
        ->set('unused', true)
        ->set('type', 'document')
        ->assertSee($nobodys->name)
        ->assertDontSee($standing->name)
        ->assertDontSee($picture->name)
        ->set('search', 'zvit')
        ->assertSee($nobodys->name)
        ->assertSet('unused', true);
});
