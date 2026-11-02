<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class FormInput
{
    public string $name = '';

    public string $id = '';

    public string $type = 'text';

    public string $value = '';

    public ?string $autocomplete = null;

    public function mount(
        string $name,
        string $id = '',
        string $type = 'text',
        string $value = '',
        ?string $autocomplete = null,
    ): void {
        $this->name = $name;
        $this->id = '' !== $id ? $id : $name;
        $this->type = $type;
        $this->value = $value;
        $this->autocomplete = $autocomplete;
    }
}
