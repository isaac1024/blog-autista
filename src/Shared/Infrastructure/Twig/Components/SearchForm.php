<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Compact GET search: a labelled field and a secondary submit button on one row.
 */
#[AsTwigComponent]
final class SearchForm
{
    public string $action = '';

    public string $name = '';

    public string $id = '';

    public string $label = '';

    public string $submit = '';

    public string $value = '';

    public function mount(string $action, string $name, string $id, string $label, string $submit, string $value = ''): void
    {
        $this->action = $action;
        $this->name = $name;
        $this->id = $id;
        $this->label = $label;
        $this->submit = $submit;
        $this->value = $value;
    }
}
