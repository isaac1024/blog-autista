<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SourceFilter;

/**
 * Lists the redirects, newest first, a page at a time, optionally only those
 * whose address has a text.
 */
final readonly class RedirectSearcher
{
    public function __construct(
        private string $source,
        private int $page,
    ) {}

    public function __invoke(RedirectRepository $redirectRepository): RedirectsResponse
    {
        $source = trim($this->source);

        return new RedirectsPage(
            '' === $source ? [] : [SourceFilter::contains($source)],
            $this->page,
        )($redirectRepository);
    }
}
