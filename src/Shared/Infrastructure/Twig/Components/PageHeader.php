<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Top bar of a page: the brand, linked to the home page, and the main menu.
 * Each is left out while missing, and the bar with them.
 */
#[AsTwigComponent]
final class PageHeader
{
    public ?string $brand = null;

    public string $homeUrl = '/';

    /**
     * @var list<array{label: string, url: string}>
     */
    public array $links = [];

    /**
     * @param list<array{label: string, url: string}> $links
     */
    public function mount(?string $brand, string $homeUrl = '/', array $links = []): void
    {
        $this->brand = $brand;
        $this->homeUrl = $homeUrl;
        $this->links = $links;
    }
}
