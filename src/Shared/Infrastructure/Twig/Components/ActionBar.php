<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Actions of a form, pinned to the bottom of the screen while the form
 * scrolls, so saving never needs a trip to the end of a long page. A status
 * next to them tells what they act on, such as whether the page is on the
 * site.
 *
 * A flag here is a presentation option of a prop, not a sign of a method
 * doing two jobs.
 *
 * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
 */
#[AsTwigComponent]
final class ActionBar
{
    /**
     * @var list<\Shared\Infrastructure\Ui\Page\PageComponent>
     */
    public array $children = [];

    public ?string $status = null;

    /**
     * Whether the form holds what was sent and refused, not what is saved:
     * it holds changes not saved from the start.
     */
    public bool $kept = false;

    /**
     * @param list<\Shared\Infrastructure\Ui\Page\PageComponent> $children
     */
    public function mount(array $children = [], ?string $status = null, bool $kept = false): void
    {
        $this->children = $children;
        $this->status = $status;
        $this->kept = $kept;
    }
}
