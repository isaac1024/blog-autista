<?php

declare(strict_types=1);

namespace Web\Post\Application;

use Web\Post\Domain\PostRepository;
use Web\Post\Domain\PostSelection;

/**
 * Finds the latest posts of a selection, as many as asked for at most,
 * newest first by the day they were first published, such as those a
 * mosaic shows.
 */
final readonly class LatestPostsFinder
{
    public function __construct(
        private PostSelection $selection,
        private int $limit,
    ) {}

    /**
     * @return list<PostCardResponse>
     */
    public function __invoke(PostRepository $postRepository): array
    {
        return array_map(PostCardResponse::fromCard(...), $postRepository->listed($this->selection, 0, $this->limit));
    }
}
