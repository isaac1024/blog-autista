<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The square picture of an author, shown at a fixed size: the browser picks
 * the copy of the srcset for it, if one is given.
 */
#[AsTwigComponent]
final class Avatar
{
    public string $src = '';

    /**
     * Every copy with its width, such as "a-96w.webp 96w, a-200w.webp 200w";
     * empty for a single file.
     */
    public string $srcset = '';

    /**
     * Side of the file at src, in pixels.
     */
    public int $size = 0;

    public string $alt = '';

    public function mount(string $src, string $srcset, int $size, string $alt): void
    {
        $this->src = $src;
        $this->srcset = $srcset;
        $this->size = $size;
        $this->alt = $alt;
    }
}
