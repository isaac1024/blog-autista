<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\AuthorRepository;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

/**
 * Finds every author, by name, such as the authors to sign a post with.
 */
final readonly class AllAuthorsFinder
{
    /**
     * @return list<AuthorResponse>
     */
    public function __invoke(AuthorRepository $authorRepository): array
    {
        $authors = [];
        $offset = 0;

        // A criteria reads a page at most, so it reads them one after another.
        do {
            $page = $authorRepository->matching(new Criteria(
                limit: Criteria::MAX_LIMIT,
                filters: [],
                order: new Order('name', OrderDirection::Ascending),
                offset: $offset,
            ));
            $authors = [...$authors, ...array_map(AuthorResponse::fromAuthor(...), $page)];
            $offset += Criteria::MAX_LIMIT;
        } while (Criteria::MAX_LIMIT === \count($page));

        return $authors;
    }
}
