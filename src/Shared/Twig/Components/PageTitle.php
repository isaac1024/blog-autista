<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Shared\Ui\Page\PageComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class PageTitle
{
    public string $title = '';

    public ?string $subtitle = null;

    public string $variant = 'default';

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
    ): void {
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->variant = $variant;
        $this->children = $children;
    }
}
