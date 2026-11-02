<?php

declare(strict_types=1);

namespace Shared\Ui\Page;

/**
 * A Twig component rendered by the generic page template.
 *
 * Components can be nested through $children: the generic renderer passes
 * them to the parent component, which decides how to paint them.
 */
final readonly class PageComponent
{
    /**
     * @param array<string, mixed> $props
     * @param list<PageComponent>  $children
     */
    public function __construct(
        public string $name,
        public array $props = [],
        public array $children = [],
    ) {}
}
