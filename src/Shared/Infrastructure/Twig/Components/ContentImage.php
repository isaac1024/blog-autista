<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * An image of written content with its caption: the browser picks the copy
 * that fits from the srcset. The one a post opens with is fetched first,
 * since it is the largest thing the reader sees as the page loads; any other
 * waits until it is about to be seen.
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
     * Whether it opens the page, so it is fetched first rather than lazily.
     */
    public bool $opening = false;

    /**
     * Whether it is the first picture of what the page shows, which the
     * browser fetches at once rather than once it nears the screen, as it
     * may be the largest thing on it as it opens.
     */
    public bool $lead = false;

    /**
     * @param list<array{href: ?string, runs: list<array{text: string, bold: bool}>}> $caption stretches of text, each linking one page or none
     */
    public function mount(string $src, string $srcset, int $width, int $height, string $alt, array $caption, bool $opening, bool $lead): void
    {
        $this->opening = $opening;
        $this->lead = $lead;
        $this->src = $src;
        $this->srcset = $srcset;
        $this->width = $width;
        $this->height = $height;
        $this->alt = $alt;
        $this->caption = $caption;
    }
}
