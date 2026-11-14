<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Shared\Domain\AvatarCopy;
use Shared\Domain\ImageCopy;
use Shared\Domain\RichText\RichText;

/**
 * How a page painted like the site's, wherever it is served, points at the
 * copies of its published images, which ImageCopy names.
 */
final class SiteMedia
{
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
        $largest = new ImageCopy($path, ImageCopy::LARGEST);

        return [
            'src' => $largest->url(),
            'srcset' => implode(', ', array_map(
                static fn (int $width): string => \sprintf('%s %dw', new ImageCopy($path, $width)->url(), $width),
                array_keys(ImageCopy::SIZES),
            )),
            'width' => $largest->width(),
            'height' => $largest->height(),
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
        $largest = new ImageCopy($path, ImageCopy::LARGEST);

        return [
            'src' => $largest->url(),
            'width' => $largest->width(),
            'height' => $largest->height(),
            'alt' => $alt,
        ];
    }

    /**
     * The avatar of an author, from its largest copy and every other one for
     * the browser to choose from.
     *
     * @param string $name the name its copies share
     *
     * @return array{src: string, srcset: string, size: int, alt: string}
     */
    public static function avatar(string $name, string $alt): array
    {
        return [
            'src' => new AvatarCopy($name, AvatarCopy::LARGEST)->url(),
            'srcset' => implode(', ', array_map(
                static fn (int $size): string => \sprintf('%s %dw', new AvatarCopy($name, $size)->url(), $size),
                AvatarCopy::SIZES,
            )),
            'size' => AvatarCopy::LARGEST,
            'alt' => $alt,
        ];
    }
}
