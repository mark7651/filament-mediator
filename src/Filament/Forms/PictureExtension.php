<?php

declare(strict_types=1);

namespace Mediator\Filament\Forms;

use Filament\Forms\Components\RichEditor\TipTapExtensions\ImageExtension;

/**
 * A picture in a text, written as this system keeps it: the width and the
 * height as attributes of the tag and nothing else.
 *
 * The editor of Filament writes the size twice, once as attributes and once
 * as a style on the tag itself. The attributes are what the page needs to keep
 * the room for a picture that has not arrived yet; the style is a decision
 * about how wide the picture is drawn, and that decision belongs to the site.
 * A style on the tag wins against every rule of a stylesheet, so a picture of
 * two thousand pixels written that way stands out of its column on a phone and
 * nothing on the site can talk it back in.
 *
 * Reading is left as Filament wrote it: a picture that came from elsewhere
 * with its size in a style still has a size, and it is written out as
 * attributes the next time the text is saved.
 */
class PictureExtension extends ImageExtension
{
    /**
     * @return array<string, array<mixed>>
     */
    public function addAttributes(): array
    {
        $attributes = parent::addAttributes();

        foreach (['width', 'height'] as $side) {
            $attributes[$side]['renderHTML'] = fn ($node): array => [
                $side => $this->pixels($node->{$side} ?? null),
            ];
        }

        return $attributes;
    }

    /**
     * The length as an attribute of a tag holds it, which is a bare number of
     * pixels. A length said in anything else, a share of the width or a size
     * of the letter, is not a number of pixels and is left out rather than
     * written down as one.
     */
    private function pixels(mixed $value): ?string
    {
        $length = $this->sanitizeStyleLength($value);

        if ($length === null) {
            return null;
        }

        return preg_match('/^(\d+)(?:\.\d+)?(?:px)?$/i', $length, $sides) === 1
            ? $sides[1]
            : null;
    }
}
