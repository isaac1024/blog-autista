<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Shared\Ui\Page\PageComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class CardList
{
    public string $label = '';

    /**
     * @var list<PageComponent>
     */
    public array $children = [];

    /**
     * @param list<PageComponent> $children
     */
    public function mount(string $label, array $children = []): void
    {
        $this->label = $label;
        $this->children = $children;
    }
}
