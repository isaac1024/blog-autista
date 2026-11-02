<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class FormButton
{
    public string $label = '';

    public string $type = 'submit';

    public function mount(string $label, string $type = 'submit'): void
    {
        $this->label = $label;
        $this->type = $type;
    }
}
