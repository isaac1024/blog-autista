<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\DomainError;

final class InvalidPageSlug extends DomainError
{
    public static function onHome(string $value): self
    {
        return new self(\sprintf('The home page is served at the root of the site, so it takes no slug, not "%s".', $value), 'errors.page.slug.on_home', []);
    }
}
