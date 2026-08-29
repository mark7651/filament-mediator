# Filament Mediator

A media library for Filament panels. One plugin registration brings three things:

- a section of the panel where files are dropped, searched, described and deleted,
- a form field that lets a record hold one file,
- a button in the rich editor that puts pictures into a text.

Files are kept in a table of their own and served straight off their disk. Pictures are redrawn on
the way in (turned upright, brought down to a sane size, written as webp) unless the project asks for
them to be kept as they arrived, and drawn again to the size they are looked at by Glide, behind
signed addresses.

The package ships Ukrainian and English.

## Requirements

PHP 8.3, Laravel 13, Filament 5.7, Livewire 4.4. Thumbnails need one of the image drivers of
Intervention (GD or Imagick).

## Installation

```bash
composer require mark7651/filament-mediator
php artisan vendor:publish --tag=mediator-migrations
php artisan migrate
```

Two tables are raised: the one named in `mediator.table`, `media` by default, which holds the files,
and the one named in `mediator.texts_table`, which holds the files standing inside texts and stays
empty until a project says which of its texts can hold them. A table that is already there is left
as it is, so a project carrying files over from another library keeps its own.

The migrations are published rather than run out of the package, because the table of the library is
the project's own: a project that wants a column, an index or a key of its own in it edits the file
it was given instead of altering what it has just raised.

They are handed over under the names the package holds them by, `0001_01_01_000000` and
`0001_01_01_000001`, and not under the hour of the publishing. The records of a project point at the
table of the library with keys of their own, so it has to stand before them; a project that wants it
raised later renames the file it was given.

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

## The wall

Files stand on a wall of cards that grows as it is scrolled, is searched by the name a file was given
and by what it says for those who cannot see it, and is narrowed by the kind of file and by whether
anything stands on it. A card opens a panel of details beside the wall: the picture itself, the two
words a person may write about it, everything the library knows of the file, and where it stands.

The wall answers the keyboard as a wall and not as a list of seventy stops. One tab reaches it and
the next leaves it; inside, the arrows walk from card to card, left and right to the neighbour, up
and down by a row. Enter and the space bar open the card under the focus, and while the panel of
details is open it follows the focus from card to card. Escape closes it and puts the focus back on
the card it was opened from. The tick of a card is reachable by tab from the card itself and stays
out of the way of every other card.

## A file in a record

```php
use Mediator\Filament\Forms\MediaField;

MediaField::make('cover_id'),
MediaField::make('icon_id')->takes(['image/svg+xml', 'image/png']),
MediaField::make('gallery')->multiple(),
```

The field keeps the number of the file in the column it is named after. Without `takes()` it opens
the whole library; with it, the wall shows those kinds alone and refuses anything else handed to it,
including a file dropped straight onto the open library.

`multiple()` makes it a run of files instead of one: the state of the field is a list of numbers in
the order they were chosen, the library opens to gather rather than to hand over the first card that
is clicked, and every file standing in the field carries a button that takes that one back out. What
the column behind it is made of is the project's business, an array cast or anything else that takes
a list of numbers. A file ticked twice is written down once.

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

### A file standing in a text

A picture put into a text by the editor is held there by its address, so no column leads to it and
nothing counts it. A project says which of its texts can hold files, and the library takes care of
the rest:

```php
app(Places::class)
    ->standsInText(Article::class, 'body')
    ->standsInText(PageTranslation::class, ['content', 'aside'], stands: fn (PageTranslation $telling): Page => $telling->page);
```

The library hangs a reading on the model itself: at the moment the record is saved the text is read
and the files found in it are written down in a table of pairs, `mediator.texts_table`. Counting a
place then costs a key of an index however far the texts of the project have grown, and reading every
text of the project to answer one question never happens.

A text lives in the telling of a page in one language while the record a person opens is the page
itself, so `stands` says which record is named and counted; without it the record that was saved is
the record that is named. A page told in two languages is one place and not two.

Both the picture and the link are read, because a text holds a file either way, and a file is matched
by the name the library gave it. A record thrown into the trash holds the pictures of its text as
firmly as one standing on the site; a record gone for good lets go of them.

A text changed past the model, by an import or by a statement written by hand, leaves the table
saying what was true before. The way back is:

```bash
php artisan mediator:relink
```

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

The record says what its row holds and nothing besides: the address of a file, the addresses of the
sizes it is drawn to and the name a person reads are worked out when they are asked for and are not
part of `toArray()`. A file on a private disk would otherwise be signed a new address every time a
record was turned into an array, and a project handing files to an API of its own shapes them the way
that API wants them anyway.

## What the project says about the records

Two things a project may say about its own library, both from the `boot()` of a provider.

### How much of it is shown

The library is one wall for everything in the table, which is right for a site and wrong for a place
where each account holds files of its own:

```php
use Illuminate\Database\Eloquent\Builder;
use Mediator\Mediator;

Mediator::scope(fn (Builder $files) => $files->whereBelongsTo(Filament::getTenant()));
```

Every wall of the library is narrowed the same way: the section, the field of a record and the button
of the editor read it through one query, so a file outside the narrowing cannot be seen, opened,
renamed, chosen or deleted from any of them.

The narrowing is a way of showing and not a way of keeping. Three things stand outside it on purpose:
the row a new file is written into, the record behind a signed thumbnail address, which is served
without a session, and the reading of the texts of the project, which happens in console commands and
queued jobs as readily as under somebody signed in. A project that has to keep one account out of the
files of another writes that into its policy as well, which is what the library asks before it acts
on a file.

### What is written into a new row

The library knows a file: the disk, the sides, the kind, the weight. Who it belongs to and where it
came from are questions it cannot answer and a column of the project may insist on:

```php
Mediator::filling(fn (array $said, UploadedFile $file): array => [
    'account_id' => auth()->user()?->account_id,
    'source' => 'panel',
]);
```

What comes back is added to what the library said, so any of it can be answered differently as well.
The same hook is asked when a new picture goes behind a record that already stands, because what a
project says of a file is often read off the picture: the shape of a banner changes with the picture
behind it.

## Who may do what

The package registers `Mediator\Policies\MediaPolicy` for the model, but only where the project has
no policy of its own for it. Laravel finds `App\Policies\MediaPolicy` for `App\Models\Media` by
itself, and where it does, the policy of the package stands aside. So a project that wants deletion
kept to administrators writes that policy and registers nothing.

The shipped policy lets any signed in user do everything. A policy of the project is asked:

| Ability | Where |
| --- | --- |
| `viewAny` | the section in the sidebar, and the wall itself wherever it is not standing in a form |
| `view` | every file the wall is about to open, walk to, rename, replace or delete |
| `create` | anything dropped on the wall |
| `update` | a name, an alt or a new picture behind a file |
| `delete` | one file, and every single file of an armful before any of the armful goes |
| `deleteAny` | whether the button that clears out the ticked files is offered at all |

A wall standing in a form is left to the form: the person is already inside a record they were
allowed to open, and the file they choose is written by that record and nowhere else.

`delete` is asked of each file of an armful because `deleteAny` is a question about the person rather
than about a file, and a project whose policy lets an editor clear out some files and not others
would otherwise have that policy answered once for a whole armful. A refusal stops the armful before
the first file of it goes.

A policy that says nothing about `view` is a project that hides no file from anyone who reached the
library, and the wall shows what its query gave it. A project that has to hide one is the project
that writes a `view()`.

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
| `texts_table` | the table of the files standing inside texts |
| `types` | every kind of file the library holds, and the extension each is written with |
| `step` | how many cards stand on the wall when it opens and are added at a time |
| `disk`, `directory`, `visibility` | where a new file is written; files are laid out in folders by year and month |
| `ceilings` | how heavy a file may be, pictures held to a tenth of the rest |
| `pictures` | the longest side a photograph is brought down to, the quality it is written with, and the kinds redrawn on the way in |
| `thumbnails` | the path the drawn pictures are served under, the token that signs the addresses, the cache folder and the named sizes |
| `private_for` | for how many minutes an address to a file written as private is good |

The library takes the kinds named in `types` and no others: the kind is read out of the bytes of the
file and the extension is written from that list, so a picture handed over as `page.html` cannot end
up served as a page of the domain the panel is signed in to. A project adds the kinds it needs there
and takes away the ones it wants kept out.

`pictures.redraw` names the kinds redrawn on the way in. A kind left out of it is written to the disk
as it arrived, byte for byte, and an empty list is a library that keeps every original: a studio
handing photographs back to the people they belong to wants exactly that, and pays for it with the
weight.

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

A file written as private is served under an address that lasts `mediator.private_for` minutes, and
the addresses of the sizes it is drawn to carry the same hour, signed along with the measures. A
thumbnail never outlives the file it was drawn from, which is the whole of what a private disk was
asked for. The hour is not one of the measures a picture is drawn by, so two addresses that differ
only in it are answered with the one picture already drawn.

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
