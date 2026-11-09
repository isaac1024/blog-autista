<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Field of one page: its title and where it stands, and a dialog to pick
 * another searched by title. Mounted by the page-field Stimulus controller,
 * which looks up the page to name it and says where it stands for the site.
 *
 * It posts the id of the page, blank for none, so the page chosen already
 * posts without JavaScript too.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[AsTwigComponent]
final class PageField
{
    public string $name = '';

    public string $id = '';

    public string $label = '';

    public string $hint = '';

    /**
     * Id of the page chosen, blank for none.
     */
    public string $value = '';

    /**
     * Where the field searches the pages to pick, and looks up the one it
     * holds, as JSON.
     */
    public string $searchUrl = '';

    public string $lookupUrl = '';

    /**
     * What is said of a page off the site, after its status.
     */
    public string $offSite = '';

    public function mount(
        string $name,
        string $id,
        string $label,
        string $hint,
        string $value,
        string $searchUrl,
        string $lookupUrl,
        string $offSite,
    ): void {
        $this->name = $name;
        $this->id = $id;
        $this->label = $label;
        $this->hint = $hint;
        $this->value = $value;
        $this->searchUrl = $searchUrl;
        $this->lookupUrl = $lookupUrl;
        $this->offSite = $offSite;
    }
}
