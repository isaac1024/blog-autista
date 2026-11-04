<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Surface that groups related content, optionally under a heading.
 */
#[AsTwigComponent]
final class Panel
{
    public ?string $title = null;

    public ?string $description = null;

    /**
     * @var list<\Shared\Ui\Page\PageComponent>
     */
    public array $children = [];

    /**
     * @param list<\Shared\Ui\Page\PageComponent> $children
     */
    public function mount(?string $title = null, ?string $description = null, array $children = []): void
    {
        $this->title = $title;
        $this->description = $description;
        $this->children = $children;
    }

    /**
     * Id of the heading that names the panel, derived from its title.
     */
    public function headingId(): string
    {
        return 'panel-'.substr(md5((string) $this->title), 0, 8);
    }
}
