<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Alert
{
    public string $message = '';

    public ?string $title = null;

    public function mount(string $message, ?string $title = null): void
    {
        $this->message = $message;
        $this->title = $title;
    }
}
