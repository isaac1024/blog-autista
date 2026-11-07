<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Row of a ResourceList: the whole row opens the resource, when it has
 * somewhere to open.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[AsTwigComponent]
final class ResourceRow
{
    public string $title = '';

    /**
     * Where the row leads; null for a row that opens nothing yet.
     */
    public ?string $url = null;

    /**
     * Secondary line, such as the address of a page.
     */
    public ?string $meta = null;

    public ?string $badge = null;

    /**
     * Where the resource stands, such as whether it is published.
     */
    public ?string $status = null;

    /**
     * Address of a 16:9 thumbnail of the resource, such as an image. It is
     * decorative: the title names the resource.
     */
    public ?string $thumbnail = null;

    public function mount(
        string $title,
        ?string $url = null,
        ?string $meta = null,
        ?string $badge = null,
        ?string $status = null,
        ?string $thumbnail = null,
    ): void {
        $this->title = $title;
        $this->url = $url;
        $this->meta = $meta;
        $this->badge = $badge;
        $this->status = $status;
        $this->thumbnail = $thumbnail;
    }
}
