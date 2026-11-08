<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A paragraph of written content.
 */
#[AsTwigComponent]
final class ContentParagraph
{
    /**
     * @var list<array{href: ?string, runs: list<array{text: string, bold: bool}>}>
     */
    public array $text = [];

    /**
     * @param list<array{href: ?string, runs: list<array{text: string, bold: bool}>}> $text stretches of text, each linking one page or none
     */
    public function mount(array $text): void
    {
        $this->text = $text;
    }
}
