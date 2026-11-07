<?php

declare(strict_types=1);

namespace Web\Page\Application;

use Shared\Domain\DomainError;

final class PageNotFound extends DomainError
{
    public static function withSlug(string $slug): self
    {
        return new self(\sprintf('No page on the site is at "%s".', $slug), 'errors.page.not_found', ['%slug%' => $slug]);
    }

    public static function home(): self
    {
        return new self('The home page is not on the site.', 'errors.page.home_not_found', []);
    }
}
