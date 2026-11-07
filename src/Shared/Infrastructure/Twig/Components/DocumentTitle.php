<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The editable title of a document, as its main heading: the title is edited
 * where it reads, at the top of the document, as in a word processor.
 *
 * The field sits inside the h1, which takes its value as its name, and joins
 * its form through the form attribute, so it can live above it.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
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
     * Where the document stands, such as whether it is published.
     */
    public ?string $status = null;

    /**
     * Length past which the title may be cut, e.g. in search results; null
     * leaves it uncounted.
     */
    public ?int $recommendedMax = null;

    /**
     * @var list<\Shared\Infrastructure\Ui\Page\PageComponent>
     */
    public array $children = [];

    /**
     * @param list<\Shared\Infrastructure\Ui\Page\PageComponent> $children actions next to the title
     */
    public function mount(
        string $name,
        string $id,
        string $label,
        string $value,
        string $form,
        ?string $badge = null,
        array $children = [],
        ?int $recommendedMax = null,
        ?string $status = null,
    ): void {
        $this->name = $name;
        $this->id = $id;
        $this->label = $label;
        $this->value = $value;
        $this->form = $form;
        $this->badge = $badge;
        $this->status = $status;
        $this->children = $children;
        $this->recommendedMax = $recommendedMax;
    }

    public function length(): int
    {
        return mb_strlen($this->value);
    }
}
