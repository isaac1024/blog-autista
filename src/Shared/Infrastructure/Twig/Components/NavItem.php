<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class NavItem
{
    public string $label = '';

    public string $url = '';

    public function mount(string $label, string $url): void
    {
        $this->label = $label;
        $this->url = $url;
    }
}
