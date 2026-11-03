<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A summary of an item whose whole surface links to it.
 */
#[AsTwigComponent]
final class Card
{
    public string $title = '';

    public string $url = '';

    public function mount(string $title, string $url): void
    {
        $this->title = $title;
        $this->url = $url;
    }
}
