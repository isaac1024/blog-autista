<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A page of the blog that written content points to, as a card of related
 * content. Its title is no heading, so it stays out of the outline of the
 * page it sits in.
 */
#[AsTwigComponent]
final class ContentRelatedPage
{
    public string $title = '';

    public string $url = '';

    public function mount(string $title, string $url): void
    {
        $this->title = $title;
        $this->url = $url;
    }
}
