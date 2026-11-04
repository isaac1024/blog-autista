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

    /**
     * Whether it spans the whole form, as on the login form; otherwise only on
     * narrow screens, where a wide target is easier to reach.
     */
    public bool $fullWidth = false;

    public function mount(string $label, string $type = 'submit', string $variant = 'primary', bool $fullWidth = false): void
    {
        $this->label = $label;
        $this->type = $type;
        $this->variant = $variant;
        $this->fullWidth = $fullWidth;
    }
}
