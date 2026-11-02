<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class PageTitle
{
    public string $title = '';

    public ?string $subtitle = null;

    public string $variant = 'default';

    public function mount(string $title, ?string $subtitle = null, string $variant = 'default'): void
    {
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->variant = $variant;
    }
}
