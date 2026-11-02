<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Shared\Ui\Page\PageComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class FormField
{
    /**
     * @var list<PageComponent>
     */
    public array $children = [];

    /**
     * @param list<PageComponent> $children
     */
    public function mount(array $children = []): void
    {
        $this->children = $children;
    }
}
