<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\NameFilter;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

/**
 * Lists one page of the authors, newest first, optionally narrowed down to
 * those whose name has the given text.
 */
final readonly class AuthorSearcher
{
    public const int PAGE_SIZE = 20;

    public function __construct(
        private string $name,
        private int $page,
    ) {}

    public function __invoke(AuthorRepository $authorRepository): AuthorsResponse
    {
        if ($this->page < 1) {
            throw InvalidCriteria::nonPositivePage($this->page);
        }

        $name = trim($this->name);

        // Author ids are time-ordered (UUID v7), so sorting by id lists the newest first.
        $criteria = new Criteria(
            filters: '' === $name ? [] : [NameFilter::contains($name)],
            order: new Order('id', OrderDirection::Descending),
            // One more than a page tells whether another follows.
            limit: self::PAGE_SIZE + 1,
            offset: ($this->page - 1) * self::PAGE_SIZE,
        );

        $authors = $authorRepository->matching($criteria);

        return new AuthorsResponse(
            array_map(AuthorResponse::fromAuthor(...), \array_slice($authors, 0, self::PAGE_SIZE)),
            \count($authors) > self::PAGE_SIZE,
        );
    }
}
