<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A row of links to the views of one page, such as the periods of a report,
 * with the one shown marked as current.
 */
#[AsTwigComponent]
final class SegmentedNav
{
    public string $label = '';

    /**
     * @var list<array{label: string, url: string, current: bool}>
     */
    public array $links = [];

    /**
     * @param list<array{label: string, url: string, current: bool}> $links
     */
    public function mount(string $label, array $links): void
    {
        $this->label = $label;
        $this->links = $links;
    }
}
