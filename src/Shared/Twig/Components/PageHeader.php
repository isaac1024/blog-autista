<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class PageHeader
{
    public string $brand = '';

    public string $homeUrl = '/';

    /**
     * @var list<array{label: string, url: string}>
     */
    public array $links = [];

    /**
     * @param list<array{label: string, url: string}> $links
     */
    public function mount(string $brand, string $homeUrl = '/', array $links = []): void
    {
        $this->brand = $brand;
        $this->homeUrl = $homeUrl;
        $this->links = $links;
    }
}
