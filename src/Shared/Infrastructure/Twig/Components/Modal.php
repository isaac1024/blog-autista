<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[AsTwigComponent]
final class Modal
{
    public string $id = '';

    public string $trigger = '';

    public string $title = '';

    public ?string $description = null;

    /**
     * Variant of the trigger button, see ModalTrigger.
     */
    public string $variant = 'primary';

    /**
     * Either 'default', for confirmations and short forms, or 'wide', for
     * forms that need room, such as one with an image to crop.
     */
    public string $size = 'default';

    /**
     * @var list<PageComponent>
     */
    public array $children = [];

    /**
     * @param list<PageComponent> $children
     */
    public function mount(
        string $id,
        string $trigger,
        string $title,
        ?string $description = null,
        string $variant = 'primary',
        string $size = 'default',
        array $children = [],
    ): void {
        $this->id = $id;
        $this->trigger = $trigger;
        $this->title = $title;
        $this->description = $description;
        $this->variant = $variant;
        $this->size = $size;
        $this->children = $children;
    }
}
