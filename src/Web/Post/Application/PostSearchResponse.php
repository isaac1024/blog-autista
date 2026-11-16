<?php

declare(strict_types=1);

namespace Web\Post\Application;

/**
 * What a search of the posts found: nothing asked, words too short to search
 * by, or a page of the posts found, the most relevant first, and whether
 * another follows it.
 */
final readonly class PostSearchResponse
{
    /**
     * @param list<PostResponse> $posts
     */
    public function __construct(
        public bool $asked,
        public bool $tooShort,
        public array $posts,
        public bool $hasMore,
    ) {}
}
