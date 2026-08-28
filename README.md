# Filament Mediator

A media library for Filament panels. One plugin registration brings three things:

- a section of the panel where files are dropped, searched, described and deleted,
- a form field that lets a record hold one file,
- a button in the rich editor that puts pictures into a text.

Files are kept in a table of their own and served straight off their disk. Pictures are redrawn on
the way in (turned upright, brought down to a sane size, written as webp) and drawn again to the size
they are looked at by Glide, behind signed addresses.

The package ships Ukrainian and English.

## Requirements

PHP 8.3, Laravel 13, Filament 5.7, Livewire 4.4. Thumbnails need one of the image drivers of
Intervention (GD or Imagick).

## Installation

```bash
composer require mark7651/filament-mediator
php artisan migrate
```

The migration creates the table named in `mediator.table`, `media` by default.

Register the plugin on the panel that gets the library:

```php
use Mediator\Filament\MediatorPlugin;

$panel->plugin(
    MediatorPlugin::make()
        ->navigationGroup(fn (): string => __('navigation.settings'))
        ->navigationSort(3),
);
```

The group is taken as a closure as well, because a group named in the language of the panel cannot be
read while the panel is still being built. The icon is `heroicon-o-photo` unless
`navigationIcon()` says otherwise.

That registration also hangs the styles of the library on the panel, so a project has nothing to add
to its own build.

## A file in a record

```php
use Mediator\Filament\Forms\MediaField;

MediaField::make('cover_id'),
MediaField::make('icon_id')->takes(['image/svg+xml', 'image/png']),
```

The field keeps the number of the file in the column it is named after. Without `takes()` it opens
the whole library; with it, the wall shows those kinds alone and refuses anything else handed to it,
including a file dropped straight onto the open library.

## A picture in a text

```php
use Filament\Forms\Components\RichEditor;
use Mediator\Filament\Forms\MediaImagePlugin;

RichEditor::make('body')
    ->plugins([MediaImagePlugin::make()])
    ->toolbarButtons([['bold', 'italic'], ['image']]),
```

The plugin supplies the tool, the form decides whether it stands in the toolbar, so `image` has to be
named in `toolbarButtons()`. Several pictures can be chosen at once and go into the text in the order
they were picked.

A picture is written as `<img src alt width height>` and never with a `style`, because the size a
picture is drawn at belongs to the site the text is shown on. Filament writes that style itself, so
the package binds `Filament\Forms\Components\RichEditor\TipTapExtensions\ImageExtension` to an
extension of its own that takes it back out and moves the size into the attributes. A project that
wants the style back binds that name to the extension of Filament in a provider of its own.

## Counting where a file stands

The library is opened to tidy up, and tidying up is deleting. A picture is chosen from inside the
record that needs it, so nothing in the panel says where a file has ended up. The register is the
answer: a project writes down the places it puts files in, and the library counts them before it
warns. A project that writes nothing down is a project where nothing is counted; the library still
deletes, the warning simply has less to say.

```php
use Mediator\Models\Media;
use Mediator\Uses\Places;

app(Places::class)
    ->standsIn(Article::class, 'cover_id', withTrashed: true)
    ->standsIn(Practice::class, 'icon_id')
    ->counted(fn (Media $file): int => Page::standingInBlocks((int) $file->getKey()));
```

A record with two columns for files is registered twice, once per column. `counted()` is for a place
no column leads to: a file standing inside the json of a page is found by reading the pages, not by
following a relation.

The panel of an open file says where it stands, not only how many places that is, and so does the
warning that stands between the file and the disk: an editor about to delete a picture reads the kind
and the name of every record that will lose it, each of them a way to the record itself. A record found
through `standsIn()` is named by its `title`, its `name` or, carrying neither, by its number, and it
is linked to its page in the panel wherever the project has a resource for that model. A place
written down with `counted()` is a number and nothing more, unless the project hands over the
records as well:

```php
app(Places::class)->counted(
    fn (Media $file): int => Page::standingIn($file)->count(),
    fn (Media $file): Collection => Page::standingIn($file)->get(),
);
```

The library is tidied by deleting, and deleting is safe only where nothing stands on the file, so the
wall has a button that leaves in it the files no record stands on. That question is put about the
whole library at once and is answered by the places rather than by the files: a place with a column
of its own answers it by itself, and a place written down with `counted()` answers it where the
project hands over the numbers of the files standing in it.

```php
app(Places::class)->counted(
    fn (Media $file): int => Page::standingIn($file)->count(),
    fn (Media $file): Collection => Page::standingIn($file)->get(),
    anywhere: fn (): array => Page::everyFileStandingInOne(),
);
```

A place that says nothing here is left out of that answer, and the files standing in it are counted
among nobody's. The register knows what the project told it and nothing else.

The register is a singleton and is filled from the `boot()` of a provider of the project.

## A model of your own

A project that needs the record to know more than the library does puts its own model in the config
and inherits the one of the package:

```php
// config/mediator.php
'model' => App\Models\Media::class,
```

```php
namespace App\Models;

use Mediator\Models\Media as LibraryMedia;

class Media extends LibraryMedia
{
    // relations, casts, whatever the project needs of it
}
```

The library never names the model itself, it asks `Mediator::model()` and `Mediator::query()`.

## Who may do what

The package registers `Mediator\Policies\MediaPolicy` for the model, but only where the project has
no policy of its own for it. Laravel finds `App\Policies\MediaPolicy` for `App\Models\Media` by
itself, and where it does, the policy of the package stands aside. So a project that wants deletion
kept to administrators writes that policy and registers nothing.

The shipped policy lets any signed in user do everything.

## Configuration

```bash
php artisan vendor:publish --tag=mediator-config
php artisan vendor:publish --tag=mediator-translations
php artisan vendor:publish --tag=mediator-views
```

What the config decides:

| Key | What it is |
| --- | --- |
| `model`, `table` | the record of a file and the table it stands on |
| `disk`, `directory`, `visibility` | where a new file is written; files are laid out in folders by year and month |
| `ceilings` | how heavy a file may be, pictures held to a tenth of the rest |
| `pictures` | the longest side a photograph is brought down to, and the quality it is written with |
| `thumbnails` | the path the drawn pictures are served under, the token that signs the addresses, the cache folder and the named sizes |

A project carrying files over from another library names that library's table in `table` and moves
nothing.

### The ceiling of Livewire

Files arrive through Livewire, which has a ceiling of its own, and it is the lower of the two that
decides. `mediator.ceilings.default` is a hundred megabytes, so the host project has to raise
Livewire to match:

```php
// config/livewire.php
'temporary_file_upload' => [
    'rules' => ['required', 'file', 'max:102400'],
],
```

The web server counts too: `upload_max_filesize` and `post_max_size` in PHP, `client_max_body_size`
in nginx.

## Thumbnails

Pictures are redrawn by Glide through a route of this package, `mediator.thumbnail`. Every address
carries a signature, so nobody outside can ask for a thousand sizes of one file and fill the disk
with them. Set `MEDIATOR_THUMBNAIL_TOKEN` to sign them with a key of their own; without it the key of
the application signs them.

The address of the file itself stays what it always was. A picture on a page is served straight off
its disk and never through this route: the redrawn sizes are for the panel.

## Testing

```bash
composer install
vendor/bin/pest
```

The bench is orchestra/testbench, which does not discover packages, so every provider the tests need
is listed in `getPackageProviders()` of `tests/TestCase.php`. Livewire has to stand after Filament
there.

## License

MIT.
