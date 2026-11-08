<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Bottom bar of a page: the footer menu and the copyright of the brand. Each
 * is left out while missing, and the bar with them.
 */
#[AsTwigComponent]
final class PageFooter
{
    public ?string $brand = null;

    public ?string $year = null;

    /**
     * @var list<array{label: string, url: string}>
     */
    public array $links = [];

    /**
     * @param list<array{label: string, url: string}> $links
     */
    public function mount(?string $brand, array $links = [], ?string $year = null): void
    {
        $this->brand = $brand;
        $this->links = $links;
        $this->year = $year;
    }
}
