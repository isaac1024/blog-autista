<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Shared\Domain\DomainError;

/**
 * Finds the titles of the pages among the ids given, as edited, without
 * reading the rest of each: an id of none, or anything that can't be an id,
 * is left out.
 */
final readonly class PageTitlesFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return array<string, string> by id
     */
    public function __invoke(PageRepository $pageRepository): array
    {
        $ids = [];

        foreach ($this->ids as $id) {
            try {
                $ids[] = new PageId($id);
            } catch (DomainError) {
                // Not the id of one: none to find.
            }
        }

        return $pageRepository->titlesOf($ids);
    }
}
