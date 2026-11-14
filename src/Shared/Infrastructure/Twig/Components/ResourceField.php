<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Field of one resource of a kind, such as a page or a post: its name and
 * where it stands, and a dialog to pick another searched by its name.
 * Mounted by the resource-field Stimulus controller, which looks up the
 * resource to name it and says where it stands for the site.
 *
 * It posts the id of the resource, blank for none, so the one chosen
 * already posts without JavaScript too.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[AsTwigComponent]
final class ResourceField
{
    public string $name = '';

    public string $id = '';

    public string $label = '';

    public string $hint = '';

    /**
     * Id of the resource chosen, blank for none.
     */
    public string $value = '';

    /**
     * Where the field searches the resources to pick, and looks up the one
     * it holds, as JSON: both answer them under the key, each with its id,
     * title, status and whether it is published, and the search takes the
     * name searched under the parameter.
     */
    public string $searchUrl = '';

    public string $lookupUrl = '';

    public string $key = '';

    public string $param = '';

    /**
     * Where the texts of the kind are translated, such as
     * "ui.resource_field.page".
     */
    public string $texts = '';

    /**
     * What is said of a resource off the site, after its status.
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
        string $key,
        string $param,
        string $texts,
        string $offSite,
    ): void {
        $this->name = $name;
        $this->id = $id;
        $this->label = $label;
        $this->hint = $hint;
        $this->value = $value;
        $this->searchUrl = $searchUrl;
        $this->lookupUrl = $lookupUrl;
        $this->key = $key;
        $this->param = $param;
        $this->texts = $texts;
        $this->offSite = $offSite;
    }
}
