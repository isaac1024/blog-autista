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

    public function mount(string $target, string $label): void
    {
        $this->target = $target;
        $this->label = $label;
    }
}
