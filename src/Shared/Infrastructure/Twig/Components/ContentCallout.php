<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A notice set apart from the written content around it, named in words by
 * its variant, so its meaning never depends on its colour.
 */
#[AsTwigComponent]
final class ContentCallout
{
    /**
     * One of the callout variants, such as 'note' or 'important'.
     */
    public string $variant = '';

    /**
     * @var list<list<array{text: string, bold: bool}>>
     */
    public array $paragraphs = [];

    /**
     * @param list<list<array{text: string, bold: bool}>> $paragraphs runs of text of each paragraph
     */
    public function mount(string $variant, array $paragraphs): void
    {
        $this->variant = $variant;
        $this->paragraphs = $paragraphs;
    }
}
