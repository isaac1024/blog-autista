<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A few headline figures side by side, each with what it counts, and a hint
 * when the figure needs one to be read right.
 */
#[AsTwigComponent]
final class Stats
{
    /**
     * @var list<array{label: string, value: string, hint?: string}>
     */
    public array $items = [];

    /**
     * @param list<array{label: string, value: string, hint?: string}> $items
     */
    public function mount(array $items): void
    {
        $this->items = $items;
    }
}
