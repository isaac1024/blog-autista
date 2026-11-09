<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Field of one optional image of the library: its thumbnail and texts, a
 * dialog to pick another searched by alternative text, and Remove to leave
 * the field without any. Mounted by the image-field Stimulus controller,
 * which looks up the image to show it and says where it stands for the site.
 *
 * It posts the id of the image, blank for none, so the image chosen already
 * posts without JavaScript too.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[AsTwigComponent]
final class ImageField
{
    public string $name = '';

    public string $id = '';

    public string $label = '';

    public string $hint = '';

    /**
     * Id of the image chosen, blank for none.
     */
    public string $value = '';

    /**
     * Where the field searches the images to pick, and looks up the one it
     * holds, as JSON.
     */
    public string $searchUrl = '';

    public string $lookupUrl = '';

    /**
     * What is said of an image off the site, after its status, such as that
     * it won't be used until it is published.
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
