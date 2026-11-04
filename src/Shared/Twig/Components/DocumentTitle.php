<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The editable title of a document, as its main heading: the title is edited
 * where it reads, at the top of the document, as in a word processor.
 *
 * The field sits inside the h1, which takes its value as its name, and joins
 * its form through the form attribute, so it can live above it.
 */
#[AsTwigComponent]
final class DocumentTitle
{
    public string $name = '';

    public string $id = '';

    public string $label = '';

    public string $value = '';

    public string $form = '';

    public ?string $badge = null;

    /**
     * @var list<\Shared\Ui\Page\PageComponent>
     */
    public array $children = [];

    /**
     * @param list<\Shared\Ui\Page\PageComponent> $children actions next to the title
     */
    public function mount(
        string $name,
        string $id,
        string $label,
        string $value,
        string $form,
        ?string $badge = null,
        array $children = [],
    ): void {
        $this->name = $name;
        $this->id = $id;
        $this->label = $label;
        $this->value = $value;
        $this->form = $form;
        $this->badge = $badge;
        $this->children = $children;
    }
}
