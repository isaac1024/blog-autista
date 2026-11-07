<?php

declare(strict_types=1);

namespace Web\Page\Application;

use Shared\Domain\DomainError;

final class PageNotFound extends DomainError
{
    public static function withSlug(string $slug): self
    {
        return new self('' === $slug ? 'The home page is not on the site.' : \sprintf('No page on the site is at "%s".', $slug));
    }
}
