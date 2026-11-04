<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * List of things to manage, one row each, easier to scan than a grid.
 */
#[AsTwigComponent]
final class ResourceList
{
    public string $label = '';

    /**
     * @var list<\Shared\Ui\Page\PageComponent>
     */
    public array $children = [];

    /**
     * @param list<\Shared\Ui\Page\PageComponent> $children ResourceRow components
     */
    public function mount(string $label, array $children = []): void
    {
        $this->label = $label;
        $this->children = $children;
    }
}
