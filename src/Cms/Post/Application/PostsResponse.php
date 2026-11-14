<?php

declare(strict_types=1);

namespace Cms\Post\Application;

/**
 * One page of posts, and whether more follow.
 */
final readonly class PostsResponse
{
    /**
     * @param list<PostResponse> $posts
     */
    public function __construct(
        public array $posts,
        public bool $hasMore,
    ) {}
}
