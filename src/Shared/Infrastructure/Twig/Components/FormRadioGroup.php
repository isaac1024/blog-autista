<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A choice between a few options, each with a line that explains it: easier
 * to understand at a glance than a drop-down.
 */
#[AsTwigComponent]
final class FormRadioGroup
{
    public string $name = '';

    public string $id = '';

    public string $legend = '';

    public string $value = '';

    /**
     * @var list<array{value: string, label: string, hint: string}>
     */
    public array $options = [];

    /**
     * @param list<array{value: string, label: string, hint: string}> $options
     */
    public function mount(string $name, string $id, string $legend, string $value, array $options): void
    {
        $this->name = $name;
        $this->id = $id;
        $this->legend = $legend;
        $this->value = $value;
        $this->options = $options;
    }
}
