<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

/**
 * One page of redirects, and whether more follow.
 */
final readonly class RedirectsResponse
{
    /**
     * @param list<RedirectResponse> $redirects
     */
    public function __construct(
        public array $redirects,
        public bool $hasMore,
    ) {}
}
