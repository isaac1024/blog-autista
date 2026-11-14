<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

/**
 * One page of terms, and whether more follow.
 */
final readonly class TermsResponse
{
    /**
     * @param list<TermResponse> $terms
     */
    public function __construct(
        public array $terms,
        public bool $hasMore,
    ) {}
}
