<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Shared\Domain\RichText\RichText;

/**
 * Where the site serves the copies of its published images, all 16:9, named
 * after the file of the CMS and their width, so any page painted like the
 * site's, wherever it is served, points at them.
 */
final class SiteMedia
{
    private const string URL = '/media/';

    /**
     * Widths of the copies, with their heights.
     */
    private const array SIZES = [400 => 225, 800 => 450, 1200 => 675];

    private const int LARGEST = 1200;

    /**
     * An embedded image as the content paints it, from its largest copy and
     * every other one for the browser to choose from.
     *
     * @param string $path where its file is kept, relative to the image storage
     *
     * @return array{src: string, srcset: string, width: int, height: int, alt: string, caption: RichText}
     */
    public static function image(string $path, string $alt, RichText $caption): array
    {
        return [
            'src' => self::copy($path, self::LARGEST),
            'srcset' => implode(', ', array_map(
                static fn (int $width): string => \sprintf('%s %dw', self::copy($path, $width), $width),
                array_keys(self::SIZES),
            )),
            'width' => self::LARGEST,
            'height' => self::SIZES[self::LARGEST],
            'alt' => $alt,
            'caption' => $caption,
        ];
    }

    /**
     * The largest copy of an image, which a page is shared with.
     *
     * @param string $path where its file is kept, relative to the image storage
     *
     * @return array{src: string, width: int, height: int, alt: string}
     */
    public static function shareImage(string $path, string $alt): array
    {
        return [
            'src' => self::copy($path, self::LARGEST),
            'width' => self::LARGEST,
            'height' => self::SIZES[self::LARGEST],
            'alt' => $alt,
        ];
    }

    private static function copy(string $path, int $width): string
    {
        return \sprintf('%s%s-%dw.webp', self::URL, preg_replace('/\.webp$/', '', $path), $width);
    }
}
