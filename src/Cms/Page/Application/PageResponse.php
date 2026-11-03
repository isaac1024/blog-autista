<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\Page;

/**
 * Read model of a page handed to the adapters, so they don't touch the aggregate.
 */
final readonly class PageResponse
{
    /**
     * @param string $slug   empty for the home page
     * @param bool   $home   whether it is the home page, which has no slug
     * @param bool   $unique whether it is one of the unique pages, which can't be deleted
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $slug,
        public bool $home,
        public bool $unique,
    ) {}

    public static function fromPage(Page $page): self
    {
        return new self(
            $page->id()->value(),
            $page->title()->value(),
            $page->slug()->value(),
            $page->type()->isHome(),
            $page->type()->isUnique(),
        );
    }
}
