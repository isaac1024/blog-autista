<?php

declare(strict_types=1);

namespace Cms\Author\Application;

/**
 * One page of authors, and whether more follow.
 */
final readonly class AuthorsResponse
{
    /**
     * @param list<AuthorResponse> $authors
     */
    public function __construct(
        public array $authors,
        public bool $hasMore,
    ) {}
}
