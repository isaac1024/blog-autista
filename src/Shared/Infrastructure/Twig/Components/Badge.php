<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Short label that classifies something, such as the type of a page. It is
 * text, so its meaning never depends on its colour.
 */
#[AsTwigComponent]
final class Badge
{
    public string $label = '';

    /**
     * Either 'neutral' or 'accent', the latter for what deserves attention.
     */
    public string $variant = 'neutral';

    public function mount(string $label, string $variant = 'neutral'): void
    {
        $this->label = $label;
        $this->variant = $variant;
    }
}
