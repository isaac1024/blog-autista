<?php

declare(strict_types=1);

namespace Cms\Page\Application;

/**
 * One page of pages, and whether more follow.
 */
final readonly class PagesResponse
{
    /**
     * @param list<PageResponse> $pages
     */
    public function __construct(
        public array $pages,
        public bool $hasMore,
    ) {}
}
