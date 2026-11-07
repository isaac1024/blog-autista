<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class PageTitle
{
    public string $title = '';

    public ?string $subtitle = null;

    public string $variant = 'default';

    /**
     * Short classification shown next to the title, such as the type of a page.
     */
    public ?string $badge = null;

    /**
     * @var list<PageComponent>
     */
    public array $children = [];

    /**
     * @param list<PageComponent> $children
     */
    public function mount(
        string $title,
        ?string $subtitle = null,
        string $variant = 'default',
        array $children = [],
        ?string $badge = null,
    ): void {
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->variant = $variant;
        $this->children = $children;
        $this->badge = $badge;
    }
}
