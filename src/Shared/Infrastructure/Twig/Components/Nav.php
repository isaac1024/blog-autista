<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Nav
{
    public ?string $label = null;

    /**
     * @var list<array{label: string, url: string}>
     */
    public array $links = [];

    /**
     * @param list<array{label: string, url: string}> $links
     */
    public function mount(array $links = [], ?string $label = null): void
    {
        $this->links = $links;
        $this->label = $label;
    }
}
