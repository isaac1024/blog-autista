<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A list within written content, numbered when its order matters.
 */
#[AsTwigComponent]
final class ContentList
{
    public bool $ordered = false;

    /**
     * @var list<list<array{href: ?string, runs: list<array{text: string, bold: bool}>}>>
     */
    public array $items = [];

    /**
     * A flag here is a presentation option of a prop, not a sign of a method
     * doing two jobs.
     *
     * @param list<list<array{href: ?string, runs: list<array{text: string, bold: bool}>}>> $items stretches of text, each linking one page or none of each item
     *
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
     */
    public function mount(bool $ordered, array $items): void
    {
        $this->ordered = $ordered;
        $this->items = $items;
    }
}
