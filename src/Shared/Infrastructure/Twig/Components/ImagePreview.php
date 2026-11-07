<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * An image shown whole at the width of its column, with its alternative
 * text, such as the one being edited.
 */
#[AsTwigComponent]
final class ImagePreview
{
    public string $src = '';

    public string $alt = '';

    /**
     * Its size in pixels, so the page keeps its room before it loads.
     */
    public int $width = 0;

    public int $height = 0;

    public function mount(string $src, string $alt, int $width, int $height): void
    {
        $this->src = $src;
        $this->alt = $alt;
        $this->width = $width;
        $this->height = $height;
    }
}
