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
     * Where the button sits: "end" for the end of the site's header, whose
     * panel lines up with the end of the bar's content; "start" for the
     * start of the CMS's sidebar, whose panel opens under the button from md
     * up. Below that, both take the width of their bar.
     */
    public string $align = 'end';
}
