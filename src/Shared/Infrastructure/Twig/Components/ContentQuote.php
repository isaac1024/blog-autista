<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A quotation within written content, of one or more paragraphs.
 */
#[AsTwigComponent]
final class ContentQuote
{
    /**
     * @var list<list<array{text: string, bold: bool}>>
     */
    public array $paragraphs = [];

    /**
     * @param list<list<array{text: string, bold: bool}>> $paragraphs runs of text of each paragraph
     */
    public function mount(array $paragraphs): void
    {
        $this->paragraphs = $paragraphs;
    }
}
