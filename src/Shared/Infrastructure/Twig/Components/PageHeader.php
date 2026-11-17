<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Top bar of a page: the brand, linked to the home page, the main menu and
 * a link to the search of the site. Each is left out while missing, and the
 * bar with them.
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

    public ?string $searchUrl = null;

    /**
     * @param list<array{label: string, url: string}> $links
     */
    public function mount(?string $brand, string $homeUrl = '/', array $links = [], ?string $searchUrl = null): void
    {
        $this->brand = $brand;
        $this->homeUrl = $homeUrl;
        $this->links = $links;
        $this->searchUrl = $searchUrl;
    }
}
