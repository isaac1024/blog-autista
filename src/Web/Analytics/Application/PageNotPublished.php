<?php

declare(strict_types=1);

namespace Web\Analytics\Application;

use Shared\Domain\DomainError;
use Web\Analytics\Domain\PageId;

final class PageNotPublished extends DomainError
{
    public static function withId(PageId $pageId): self
    {
        return new self(\sprintf('No page on the site has the id "%s".', $pageId->value()), 'errors.visit.page_not_published', ['%id%' => $pageId->value()]);
    }
}
