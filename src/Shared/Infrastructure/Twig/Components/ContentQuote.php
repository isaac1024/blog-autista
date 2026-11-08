<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A quotation within written content, of one or more paragraphs, with its
 * source as the caption when it has one.
 */
#[AsTwigComponent]
final class ContentQuote
{
    /**
     * @var list<list<array{href: ?string, runs: list<array{text: string, bold: bool}>}>>
     */
    public array $paragraphs = [];

    public ?string $source = null;

    /**
     * @param list<list<array{href: ?string, runs: list<array{text: string, bold: bool}>}>> $paragraphs stretches of text, each linking one page or none of each paragraph
     * @param null|string                                                                   $source     who said it, or where it comes from
     */
    public function mount(array $paragraphs, ?string $source): void
    {
        $this->paragraphs = $paragraphs;
        $this->source = $source;
    }
}
