<?php

declare(strict_types=1);

namespace Cms\Relation\Application;

use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Shared\Domain\Criteria\InvalidCriteria;

/**
 * Finds one page of the resources of the CMS that use the one given: pages
 * first, then images, then the settings, then the authors, then the posts.
 */
final readonly class UsersFinder
{
    public const int PAGE_SIZE = 20;

    public function __construct(
        private ResourceType $type,
        private string $id,
        private int $page,
    ) {}

    public function __invoke(RelationRepository $relationRepository): UsersResponse
    {
        if ($this->page < 1) {
            throw InvalidCriteria::nonPositivePage($this->page);
        }

        // One more than a page tells whether another follows.
        $relations = $relationRepository->usersOf($this->type, $this->id, self::PAGE_SIZE + 1, ($this->page - 1) * self::PAGE_SIZE);

        return new UsersResponse(
            array_map(UserResponse::fromRelation(...), \array_slice($relations, 0, self::PAGE_SIZE)),
            \count($relations) > self::PAGE_SIZE,
        );
    }
}
