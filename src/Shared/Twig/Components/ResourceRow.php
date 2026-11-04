<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Row of a ResourceList: the whole row opens the resource.
 */
#[AsTwigComponent]
final class ResourceRow
{
    public string $title = '';

    public string $url = '';

    /**
     * Secondary line, such as the address of a page.
     */
    public ?string $meta = null;

    public ?string $badge = null;

    public function mount(string $title, string $url, ?string $meta = null, ?string $badge = null): void
    {
        $this->title = $title;
        $this->url = $url;
        $this->meta = $meta;
        $this->badge = $badge;
    }
}
