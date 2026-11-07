<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Brand
{
    public string $brand = '';

    public ?string $homeUrl = null;

    public function mount(string $brand, ?string $homeUrl = null): void
    {
        $this->brand = $brand;
        $this->homeUrl = $homeUrl;
    }
}
