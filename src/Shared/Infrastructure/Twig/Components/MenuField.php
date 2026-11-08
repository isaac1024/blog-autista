<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Field of a menu of pages: its entries in order, each a page and an optional
 * label, moved with Up and Down and taken out with Remove, and a dialog to
 * add pages searched by title. Mounted by the menu-field Stimulus controller,
 * which looks up the pages to name them and says where each stands for the
 * site.
 *
 * Each entry posts as {name}[i][page_id] and {name}[i][label], so the entries
 * written already post without JavaScript too.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[AsTwigComponent]
final class MenuField
{
    public string $name = '';

    public string $id = '';

    public string $label = '';

    public string $hint = '';

    /**
     * @var list<array{page_id: string, label: ?string}>
     */
    public array $items = [];

    /**
     * Where the field searches pages to add, and looks up those it holds, as JSON.
     */
    public string $searchUrl = '';

    public string $lookupUrl = '';

    public int $maxItems = 0;

    public int $labelMaxLength = 0;

    /**
     * @param list<array{page_id: string, label: ?string}> $items
     */
    public function mount(
        string $name,
        string $id,
        string $label,
        string $hint,
        array $items,
        string $searchUrl,
        string $lookupUrl,
        int $maxItems,
        int $labelMaxLength,
    ): void {
        $this->name = $name;
        $this->id = $id;
        $this->label = $label;
        $this->hint = $hint;
        $this->items = $items;
        $this->searchUrl = $searchUrl;
        $this->lookupUrl = $lookupUrl;
        $this->maxItems = $maxItems;
        $this->labelMaxLength = $labelMaxLength;
    }
}
