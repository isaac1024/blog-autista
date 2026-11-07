<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

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
     * Presentation option: default, featured or disclosure.
     */
    public string $variant = 'default';

    /**
     * @var list<\Shared\Infrastructure\Ui\Page\PageComponent>
     */
    public array $children = [];

    /**
     * @param list<\Shared\Infrastructure\Ui\Page\PageComponent> $children
     */
    public function mount(?string $title = null, ?string $description = null, array $children = [], string $variant = 'default'): void
    {
        $this->title = $title;
        $this->description = $description;
        $this->children = $children;
        $this->variant = $variant;
    }

    /**
     * Id of the heading that names the panel, derived from its title.
     */
    public function headingId(): string
    {
        return 'panel-'.substr(md5((string) $this->title), 0, 8);
    }
}
