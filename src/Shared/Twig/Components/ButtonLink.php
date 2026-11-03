<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Navigation styled as a secondary button, for actions that lead to another URL.
 */
#[AsTwigComponent]
final class ButtonLink
{
    public string $url = '';

    public string $label = '';

    public function mount(string $url, string $label): void
    {
        $this->url = $url;
        $this->label = $label;
    }
}
