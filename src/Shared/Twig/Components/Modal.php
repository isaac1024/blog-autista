<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Shared\Ui\Page\PageComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Modal
{
    public string $id = '';

    public string $trigger = '';

    public string $title = '';

    public ?string $description = null;

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
        array $children = [],
    ): void {
        $this->id = $id;
        $this->trigger = $trigger;
        $this->title = $title;
        $this->description = $description;
        $this->children = $children;
    }
}
