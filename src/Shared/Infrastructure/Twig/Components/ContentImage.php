<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * An image of written content with its caption: the browser picks the copy
 * that fits from the srcset.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[AsTwigComponent]
final class ContentImage
{
    public string $src = '';

    /**
     * Every copy with its width, such as "a-400w.webp 400w, a-800w.webp 800w".
     */
    public string $srcset = '';

    public int $width = 0;

    public int $height = 0;

    public string $alt = '';

    /**
     * @var list<array{href: ?string, runs: list<array{text: string, bold: bool}>}>
     */
    public array $caption = [];

    /**
     * @param list<array{href: ?string, runs: list<array{text: string, bold: bool}>}> $caption stretches of text, each linking one page or none
     */
    public function mount(string $src, string $srcset, int $width, int $height, string $alt, array $caption): void
    {
        $this->src = $src;
        $this->srcset = $srcset;
        $this->width = $width;
        $this->height = $height;
        $this->alt = $alt;
        $this->caption = $caption;
    }
}
