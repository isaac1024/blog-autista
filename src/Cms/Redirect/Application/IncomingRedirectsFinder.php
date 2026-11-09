<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\ResourceType;
use Cms\Redirect\Domain\TargetFilter;

/**
 * Lists the redirects that lead to a resource, newest first, a page at a
 * time: while there is any, it stays on the site.
 */
final readonly class IncomingRedirectsFinder
{
    public function __construct(
        private ResourceType $type,
        private string $id,
        private int $page,
    ) {}

    public function __invoke(RedirectRepository $redirectRepository): RedirectsResponse
    {
        return new RedirectsPage(
            [TargetFilter::type($this->type), TargetFilter::id($this->id)],
            $this->page,
        )($redirectRepository);
    }
}
