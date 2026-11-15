<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\IdFilter;
use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Domain\TitleFilter;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

/**
 * Lists one page of results, newest first, optionally narrowed down to the
 * posts whose title has every word of the given text.
 */
final readonly class PostSearcher
{
    public const int PAGE_SIZE = 20;

    public function __construct(
        private string $title,
        private int $page,
        private ?string $except,
    ) {}

    public function __invoke(PostRepository $postRepository): PostsResponse
    {
        if ($this->page < 1) {
            throw InvalidCriteria::nonPositivePage($this->page);
        }

        $title = trim($this->title);
        $filters = '' === $title ? [] : [TitleFilter::matches($title)];

        if (null !== $this->except) {
            $filters[] = IdFilter::isNot(new PostId($this->except));
        }

        // Post ids are time-ordered (UUID v7), so sorting by id lists the newest first.
        $criteria = new Criteria(
            filters: $filters,
            order: new Order('id', OrderDirection::Descending),
            // One more than a page tells whether another follows.
            limit: self::PAGE_SIZE + 1,
            offset: ($this->page - 1) * self::PAGE_SIZE,
        );

        $posts = $postRepository->matching($criteria);

        return new PostsResponse(
            array_map(PostResponse::fromPost(...), \array_slice($posts, 0, self::PAGE_SIZE)),
            \count($posts) > self::PAGE_SIZE,
        );
    }
}
