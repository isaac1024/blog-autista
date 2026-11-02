<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class EmptyState
{
    public string $message = '';

    public function mount(string $message): void
    {
        $this->message = $message;
    }
}
