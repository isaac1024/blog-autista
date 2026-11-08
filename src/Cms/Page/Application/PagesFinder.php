<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Shared\Domain\DomainError;

/**
 * Finds the pages among those given by id, in one go and in the order given,
 * each once: an id of no page, or not an id at all, is left out.
 */
final readonly class PagesFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return list<PageResponse>
     */
    public function __invoke(PageRepository $pageRepository): array
    {
        $ids = [];

        foreach (array_unique($this->ids) as $id) {
            try {
                $ids[] = new PageId($id);
            } catch (DomainError) {
                // Not the id of a page: none to find.
            }
        }

        $found = [];

        foreach ($pageRepository->findByIds($ids) as $page) {
            $found[$page->id()->value()] = PageResponse::fromPage($page);
        }

        // The repository answers in its own order.
        return array_values(array_filter(array_map(
            static fn (PageId $id): ?PageResponse => $found[$id->value()] ?? null,
            $ids,
        )));
    }
}
