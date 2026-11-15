<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Field of a menu: its entries in order, each leading to a content of the
 * blog, a page, a post, a section or a tag, an author or an external
 * resource, with an optional label, moved with Up and Down and taken out
 * with Remove, and a dialog to add contents of each kind, searched by their
 * titles or names. Mounted by the menu-field Stimulus controller, which
 * looks up what the entries lead to to name them and says where each
 * stands for the site.
 *
 * Each entry posts as {name}[i][{type}_id], such as {name}[i][page_id], and
 * {name}[i][label], so the entries written already post without JavaScript
 * too.
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
     * @var list<array{type: string, id: string, label: ?string}>
     */
    public array $items = [];

    /**
     * The kinds of content an entry may lead to, each with where it is
     * searched and looked up, as JSON, as a text links them.
     *
     * @var list<array{kind: string, type: string, searchUrl: string, param: string, key: string, lookupUrl: string}>
     */
    public array $targets = [];

    public int $maxItems = 0;

    public int $labelMaxLength = 0;

    /**
     * @param list<array{type: string, id: string, label: ?string}>                                                     $items
     * @param list<array{kind: string, type: string, searchUrl: string, param: string, key: string, lookupUrl: string}> $targets
     */
    public function mount(
        string $name,
        string $id,
        string $label,
        string $hint,
        array $items,
        array $targets,
        int $maxItems,
        int $labelMaxLength,
    ): void {
        $this->name = $name;
        $this->id = $id;
        $this->label = $label;
        $this->hint = $hint;
        $this->items = $items;
        $this->targets = $targets;
        $this->maxItems = $maxItems;
        $this->labelMaxLength = $labelMaxLength;
    }
}
