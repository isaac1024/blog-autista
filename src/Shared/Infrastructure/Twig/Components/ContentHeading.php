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
     * @var list<array{href: ?string, runs: list<array{text: string, bold: bool}>}>
     */
    public array $text = [];

    public ?string $anchor = null;

    /**
     * @param list<array{href: ?string, runs: list<array{text: string, bold: bool}>}> $text   stretches of text, each linking one page or none
     * @param ?string                                                                 $anchor the id a link to it leads to, as HeadingAnchors gives it
     */
    public function mount(int $level, array $text, ?string $anchor): void
    {
        $this->level = $level;
        $this->text = $text;
        $this->anchor = $anchor;
    }
}
