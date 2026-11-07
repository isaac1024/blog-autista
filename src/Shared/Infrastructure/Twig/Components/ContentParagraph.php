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
     * @var list<array{text: string, bold: bool}>
     */
    public array $text = [];

    /**
     * @param list<array{text: string, bold: bool}> $text runs of text
     */
    public function mount(array $text): void
    {
        $this->text = $text;
    }
}
