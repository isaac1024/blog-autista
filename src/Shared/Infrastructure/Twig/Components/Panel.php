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
     * A disclosure opens by its title, and a description is painted under
     * the title it describes, so neither goes without one.
     *
     * @param list<\Shared\Infrastructure\Ui\Page\PageComponent> $children
     */
    public function mount(?string $title = null, ?string $description = null, array $children = [], string $variant = 'default'): void
    {
        $untitled = null === $title || '' === $title;

        if ($untitled && 'disclosure' === $variant) {
            throw new \InvalidArgumentException('A disclosure panel needs a title to open it by.');
        }

        if ($untitled && null !== $description) {
            throw new \InvalidArgumentException('A panel needs a title for its description to be painted under.');
        }

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
