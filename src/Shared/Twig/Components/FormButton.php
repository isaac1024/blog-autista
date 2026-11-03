<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class FormButton
{
    public string $label = '';

    public string $type = 'submit';

    /**
     * Either 'primary' or 'danger', the latter for destructive actions.
     */
    public string $variant = 'primary';

    public function mount(string $label, string $type = 'submit', string $variant = 'primary'): void
    {
        $this->label = $label;
        $this->type = $type;
        $this->variant = $variant;
    }
}
