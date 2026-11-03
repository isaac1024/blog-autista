<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\Page;

/**
 * Read model of a page handed to the adapters, so they don't touch the aggregate.
 */
final readonly class PageResponse
{
    public function __construct(
        public string $id,
        public string $title,
        public string $slug,
    ) {}

    public static function fromPage(Page $page): self
    {
        return new self($page->id()->value(), $page->title()->value(), $page->slug()->value());
    }
}
