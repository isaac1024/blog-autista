<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Content that only some need, folded away behind its summary until asked
 * for, such as advanced settings.
 */
#[AsTwigComponent]
final class Disclosure
{
    public string $summary = '';

    /**
     * @var list<\Shared\Ui\Page\PageComponent>
     */
    public array $children = [];

    /**
     * @param list<\Shared\Ui\Page\PageComponent> $children
     */
    public function mount(string $summary, array $children = []): void
    {
        $this->summary = $summary;
        $this->children = $children;
    }
}
