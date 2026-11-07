<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A column of written content, set in readable lines, with the same styles
 * the editor shows it with. Its children are the content blocks.
 */
#[AsTwigComponent]
final class Prose
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
