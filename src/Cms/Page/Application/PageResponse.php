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
     * @param string                                                  $slug            empty for the home page
     * @param string                                                  $type            one of the PageType values
     * @param bool                                                    $home            whether it is the home page, which has no slug
     * @param bool                                                    $unique          whether it is one of the unique pages, which can't be deleted
     * @param array{version: int, blocks: list<array<string, mixed>>} $content         in the stored rich text format
     * @param string                                                  $metaDescription empty when there is none
     * @param string                                                  $robots          one of the Robots values
     * @param string                                                  $canonicalUrl    empty when the page's own address is canonical
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $slug,
        public string $type,
        public bool $home,
        public bool $unique,
        public array $content,
        public string $metaDescription,
        public string $robots,
        public string $canonicalUrl,
    ) {}

    public static function fromPage(Page $page): self
    {
        return new self(
            $page->id()->value(),
            $page->title()->value(),
            $page->slug()->value(),
            $page->type()->value,
            $page->type()->isHome(),
            $page->type()->isUnique(),
            $page->content()->toArray(),
            $page->seo()->description() ?? '',
            $page->seo()->robots()->value,
            $page->seo()->canonicalUrl() ?? '',
        );
    }
}
