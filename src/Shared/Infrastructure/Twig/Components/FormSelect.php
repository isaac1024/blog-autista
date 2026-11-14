<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A list to pick one option from, its first option standing for none, such
 * as the section of a post.
 */
#[AsTwigComponent]
final class FormSelect
{
    public string $name = '';

    public string $id = '';

    /**
     * The value of the option picked, blank for none.
     */
    public string $value = '';

    /**
     * What the option for none says.
     */
    public string $none = '';

    /**
     * @var list<array{value: string, label: string}>
     */
    public array $options = [];

    /**
     * @param list<array{value: string, label: string}> $options
     */
    public function mount(string $name, string $id, string $value, string $none, array $options): void
    {
        $this->name = $name;
        $this->id = $id;
        $this->value = $value;
        $this->none = $none;
        $this->options = $options;
    }
}
