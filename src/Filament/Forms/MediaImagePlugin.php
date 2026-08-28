<?php

declare(strict_types=1);

namespace Mediator\Filament\Forms;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Mediator\Mediator;
use Mediator\Models\Media;
use Mediator\Uploads\Upload;
use Tiptap\Core\Extension;

/**
 * A tool in the editor's own toolbar that puts pictures of the library into the
 * text.
 *
 * The library opens as it does everywhere else, only gathering several files at
 * once: a text is written with a run of pictures in it, and opening the library
 * anew for each of them is the same deed done four times.
 *
 * What goes into the text is a plain img with the address of the file, what the
 * library says the picture shows, and the size the file actually is. Nothing
 * about how it sits on the page: where a picture stands, how wide it is drawn
 * and whether it carries a caption is the business of the site, and a page
 * whose editor decided that is a page the site cannot lay out again.
 */
class MediaImagePlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('image')
                ->label(__('mediator::media.actions.image'))
                ->icon(Heroicon::Photo)
                ->activeStyling(false)
                ->action(),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [
            Action::make('image')
                ->label(__('mediator::media.actions.image'))
                ->modalHeading(__('mediator::media.plural_title'))
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->modalWidth(Width::SevenExtraLarge)
                ->modalContent(fn (RichEditor $component): View => view('mediator::editor-library', [
                    'key' => $component->getKey(),
                    'takes' => self::pictures(),
                ]))
                ->action(function (array $arguments, RichEditor $component): void {
                    $tags = self::tags(self::numbers($arguments['ids'] ?? []));

                    if ($tags === '') {
                        return;
                    }

                    $component->runCommands(
                        [EditorCommand::make('insertContent', arguments: [$tags])],
                        // Where the editor was standing when the library was
                        // opened, without which the pictures would land at the
                        // top of the text rather than where they were asked for.
                        editorSelection: $arguments['editorSelection'] ?? null,
                    );
                }),
        ];
    }

    /**
     * The kinds of file that can stand in a text, which is every kind of
     * picture the library holds and nothing else: a text carries a film by
     * linking to it, not by holding it.
     *
     * @return list<string>
     */
    public static function pictures(): array
    {
        return array_values(array_filter(
            Upload::takes(),
            fn (string $type): bool => str_starts_with($type, 'image/'),
        ));
    }

    /**
     * The chosen files written as the markup the text takes, in the order they
     * were chosen.
     *
     * The ids come from the browser, so what each of them stands for is read
     * off the record: the button takes pictures, whatever it was handed.
     *
     * @param  list<int>  $ids
     */
    public static function tags(array $ids): string
    {
        /** @var Collection<int|string, Media> $files */
        $files = Mediator::query()->whereKey($ids)->get()->keyBy('id');

        $tags = array_map(function (int $id) use ($files): ?string {
            $file = $files->get($id);

            return $file instanceof Media && in_array((string) $file->type, self::pictures(), true)
                ? self::tag($file)
                : null;
        }, $ids);

        return implode('', array_filter($tags));
    }

    /**
     * One picture as the text holds it. The width and the height are the ones
     * the file has, so the page keeps the room for the picture before the
     * picture itself arrives; a file the library could not measure, which is
     * every drawing, goes in without them.
     */
    private static function tag(Media $file): string
    {
        $size = $file->width && $file->height
            ? ' width="'.(int) $file->width.'" height="'.(int) $file->height.'"'
            : '';

        return '<img src="'.e((string) $file->url).'" alt="'.e((string) $file->alt).'"'.$size.'>';
    }

    /**
     * @return list<int>
     */
    private static function numbers(mixed $ids): array
    {
        return array_values(array_map(
            intval(...),
            array_filter(is_array($ids) ? $ids : [], is_numeric(...)),
        ));
    }
}
