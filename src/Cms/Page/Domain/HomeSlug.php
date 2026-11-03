<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

/**
 * Address of the home page, which is served at the root of the site and so has
 * no slug: its value is empty.
 */
final readonly class HomeSlug implements PageSlug
{
    #[\Override]
    public function value(): string
    {
        return '';
    }
}
