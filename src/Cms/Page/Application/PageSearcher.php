<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\TitleFilter;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

/**
 * Lists one page of results, newest first, optionally narrowed down to the
 * pages whose title has every word of the given text.
 */
final readonly class PageSearcher
{
    public const int PAGE_SIZE = 20;

    public function __construct(
        private string $title,
        private int $page,
    ) {}

    /**
     * @return list<PageResponse>
     */
    public function __invoke(PageRepository $pageRepository): array
    {
        if ($this->page < 1) {
            throw InvalidCriteria::nonPositivePage($this->page);
        }

        $title = trim($this->title);

        // Page ids are time-ordered (UUID v7), so sorting by id lists the newest first.
        $criteria = new Criteria(
            filters: '' === $title ? [] : [TitleFilter::matches($title)],
            order: new Order('id', OrderDirection::Descending),
            limit: self::PAGE_SIZE,
            offset: ($this->page - 1) * self::PAGE_SIZE,
        );

        return array_map(PageResponse::fromPage(...), $pageRepository->matching($criteria));
    }
}
