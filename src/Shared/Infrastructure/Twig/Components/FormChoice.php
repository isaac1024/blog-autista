<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A choice among options, each a radio with its name and hint, where an
 * option may bring fields of its own, shown only while it is chosen. The
 * fields of the options not chosen still post, so whoever reads the form
 * takes those of the chosen one.
 *
 * It shows and hides them with CSS alone, so it needs no script.
 */
#[AsTwigComponent]
final class FormChoice
{
    public string $name = '';

    public string $id = '';

    public string $legend = '';

    public string $value = '';

    /**
     * @var list<array{value: string, label: string, hint: string, fields: list<PageComponent>}>
     */
    public array $options = [];

    /**
     * @param list<array{value: string, label: string, hint: string, fields: list<PageComponent>}> $options
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
