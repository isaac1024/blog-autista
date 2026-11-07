<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Single-line field, optionally framed by fixed text before and after it.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[AsTwigComponent]
final class FormInput
{
    public string $name = '';

    public string $id = '';

    public string $type = 'text';

    public string $value = '';

    public ?string $autocomplete = null;

    /**
     * Fixed text shown before and after the value, such as the parts of an
     * address around a slug.
     */
    public ?string $prefix = null;

    public ?string $suffix = null;

    public function mount(
        string $name,
        string $id = '',
        string $type = 'text',
        string $value = '',
        ?string $autocomplete = null,
        ?string $prefix = null,
        ?string $suffix = null,
    ): void {
        $this->name = $name;
        $this->id = '' !== $id ? $id : $name;
        $this->type = $type;
        $this->value = $value;
        $this->autocomplete = $autocomplete;
        $this->prefix = $prefix;
        $this->suffix = $suffix;
    }
}
