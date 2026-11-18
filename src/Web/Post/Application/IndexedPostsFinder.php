<?php

declare(strict_types=1);

namespace Web\Post\Application;

use Web\Post\Domain\PostRepository;

/**
 * Finds the latest posts search engines may index, as many as asked for at
 * most, newest first by the day they were first published, such as those
 * the feed of the site tells.
 */
final readonly class IndexedPostsFinder
{
    public function __construct(private int $limit) {}

    /**
     * @return list<PostCardResponse>
     */
    public function __invoke(PostRepository $postRepository): array
    {
        return array_map(PostCardResponse::fromCard(...), $postRepository->indexed($this->limit));
    }
}
