<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The reading settings of a header: the size of the text and the theme,
 * which reading.js shows and keeps in the reader's browser.
 */
#[AsTwigComponent]
final class ReadingSettings
{
    /**
     * The side of the button the panel lines up with from md up: "end" for
     * a button at the end of a bar, "start" for one at the start of a
     * sidebar. Below md it lines up with the end, where the button sits.
     */
    public string $align = 'end';
}
