<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Actions of a form, pinned to the bottom of the screen while the form
 * scrolls, so saving never needs a trip to the end of a long page.
 */
#[AsTwigComponent]
final class ActionBar
{
    /**
     * @var list<\Shared\Ui\Page\PageComponent>
     */
    public array $children = [];

    /**
     * @param list<\Shared\Ui\Page\PageComponent> $children
     */
    public function mount(array $children = []): void
    {
        $this->children = $children;
    }
}
