<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A heading within written content, below the title of the page.
 */
#[AsTwigComponent]
final class ContentHeading
{
    public int $level = 2;

    /**
     * @var list<array{text: string, bold: bool}>
     */
    public array $text = [];

    /**
     * @param list<array{text: string, bold: bool}> $text runs of text
     */
    public function mount(int $level, array $text): void
    {
        $this->level = $level;
        $this->text = $text;
    }
}
