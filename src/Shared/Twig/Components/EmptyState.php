<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Shared\Ui\Page\PageComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Explains why a list is empty and offers the next step to take.
 */
#[AsTwigComponent]
final class EmptyState
{
    public string $title = '';

    public string $message = '';

    /**
     * @var list<PageComponent>
     */
    public array $children = [];

    /**
     * @param list<PageComponent> $children
     */
    public function mount(string $title, string $message, array $children = []): void
    {
        $this->title = $title;
        $this->message = $message;
        $this->children = $children;
    }
}
