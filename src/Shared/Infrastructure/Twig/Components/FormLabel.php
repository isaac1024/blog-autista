<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class FormLabel
{
    public string $for = '';

    public string $text = '';

    public function mount(string $for, string $text): void
    {
        $this->for = $for;
        $this->text = $text;
    }
}
