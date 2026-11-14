<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Field of a list of terms, such as the tags of a post: each named and where
 * it stands for the site, taken out with Remove, and a dialog to add terms
 * searched by name. Mounted by the terms-field Stimulus controller.
 *
 * Each term posts its id as {name}[], so the terms chosen already post
 * without JavaScript too.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[AsTwigComponent]
final class TermsField
{
    public string $name = '';

    public string $id = '';

    public string $label = '';

    public string $hint = '';

    /**
     * The terms chosen, in order, each with where it stands, and what is said
     * of it while it is off the site, blank while it is on it.
     *
     * @var list<array{id: string, name: string, status: string, note: string}>
     */
    public array $items = [];

    /**
     * Where the field searches the terms to add, as JSON.
     */
    public string $searchUrl = '';

    /**
     * What is said of a term off the site, after its status.
     */
    public string $offSite = '';

    /**
     * @param list<array{id: string, name: string, status: string, note: string}> $items
     */
    public function mount(
        string $name,
        string $id,
        string $label,
        string $hint,
        array $items,
        string $searchUrl,
        string $offSite,
    ): void {
        $this->name = $name;
        $this->id = $id;
        $this->label = $label;
        $this->hint = $hint;
        $this->items = $items;
        $this->searchUrl = $searchUrl;
        $this->offSite = $offSite;
    }
}
