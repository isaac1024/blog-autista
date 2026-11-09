<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Moves through the pages of a list: the one before, where it stands, and
 * the one after, each link only while there is a page to go to.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[AsTwigComponent]
final class Pagination
{
    /**
     * Names the navigation, such as the list it moves through.
     */
    public string $label = '';

    /**
     * Where the list stands, such as "Page 2".
     */
    public string $current = '';

    public ?string $previousUrl = null;

    public string $previousLabel = '';

    public ?string $nextUrl = null;

    public string $nextLabel = '';

    public function mount(
        string $label,
        string $current,
        ?string $previousUrl,
        string $previousLabel,
        ?string $nextUrl,
        string $nextLabel,
    ): void {
        $this->label = $label;
        $this->current = $current;
        $this->previousUrl = $previousUrl;
        $this->previousLabel = $previousLabel;
        $this->nextUrl = $nextUrl;
        $this->nextLabel = $nextLabel;
    }
}
