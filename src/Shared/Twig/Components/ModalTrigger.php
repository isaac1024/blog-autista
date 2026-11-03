<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Primary button that opens a Modal through the Invoker Commands API, so it
 * needs no JavaScript and can live anywhere on the page.
 */
#[AsTwigComponent]
final class ModalTrigger
{
    public string $target = '';

    public string $label = '';

    /**
     * Either 'primary' or 'danger', the latter for modals that confirm a
     * destructive action.
     */
    public string $variant = 'primary';

    public function mount(string $target, string $label, string $variant = 'primary'): void
    {
        $this->target = $target;
        $this->label = $label;
        $this->variant = $variant;
    }
}
