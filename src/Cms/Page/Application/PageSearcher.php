<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\IdFilter;
use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\TitleFilter;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

/**
 * Lists one page of results, newest first, optionally narrowed down to the
 * pages whose title has every word of the given text, and leaving one out,
 * such as the page that searches others to link.
 */
final readonly class PageSearcher
{
    public const int PAGE_SIZE = 20;

    public function __construct(
        private string $title,
        private int $page,
        private ?string $except,
    ) {}

    public function __invoke(PageRepository $pageRepository): PagesResponse
    {
        if ($this->page < 1) {
            throw InvalidCriteria::nonPositivePage($this->page);
        }

        $title = trim($this->title);
        $filters = '' === $title ? [] : [TitleFilter::matches($title)];

        if (null !== $this->except) {
            $filters[] = IdFilter::isNot(new PageId($this->except));
        }

        // Page ids are time-ordered (UUID v7), so sorting by id lists the newest first.
        $criteria = new Criteria(
            filters: $filters,
            order: new Order('id', OrderDirection::Descending),
            // One more than a page tells whether another follows.
            limit: self::PAGE_SIZE + 1,
            offset: ($this->page - 1) * self::PAGE_SIZE,
        );

        $pages = $pageRepository->matching($criteria);

        return new PagesResponse(
            array_map(PageResponse::fromPage(...), \array_slice($pages, 0, self::PAGE_SIZE)),
            \count($pages) > self::PAGE_SIZE,
        );
    }
}
