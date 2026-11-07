<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Help text for a form field, linked to its input through aria-describedby.
 */
#[AsTwigComponent]
final class FormHint
{
    public string $id = '';

    public string $text = '';

    public function mount(string $id, string $text): void
    {
        $this->id = $id;
        $this->text = $text;
    }
}
