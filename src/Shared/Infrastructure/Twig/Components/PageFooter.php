<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class PageFooter
{
    public string $brand = '';

    public ?string $year = null;

    public function mount(string $brand, ?string $year = null): void
    {
        $this->brand = $brand;
        $this->year = $year;
    }
}
