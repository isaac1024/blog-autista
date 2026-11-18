<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;
use Shared\Domain\DomainError;

/**
 * Finds the titles of the posts among the ids given, as edited, without
 * reading the rest of each: an id of none, or anything that can't be an id,
 * is left out.
 */
final readonly class PostTitlesFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return array<string, string> by id
     */
    public function __invoke(PostRepository $postRepository): array
    {
        $ids = [];

        foreach ($this->ids as $id) {
            try {
                $ids[] = new PostId($id);
            } catch (DomainError) {
                // Not the id of one: none to find.
            }
        }

        return $postRepository->titlesOf($ids);
    }
}
