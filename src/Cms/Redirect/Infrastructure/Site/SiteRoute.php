<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Site;

/**
 * A route of the site that answers an address, with the slug of the page it
 * names when it is the route of the pages, which only answers while that
 * page is on the site.
 */
final readonly class SiteRoute
{
    public function __construct(public ?string $pageSlug) {}
}
