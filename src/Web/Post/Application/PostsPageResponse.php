<?php

declare(strict_types=1);

namespace Web\Post\Application;

/**
 * A page of a list of posts of the site, newest first, and whether another
 * follows it.
 */
final readonly class PostsPageResponse
{
    /**
     * @param list<PostCardResponse> $posts
     */
    public function __construct(
        public array $posts,
        public bool $hasMore,
    ) {}
}
