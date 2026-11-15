<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\DomainError;

final class InvalidPageContent extends DomainError
{
    public static function linksItself(): self
    {
        return new self('A page cannot link to itself.', 'errors.page.content.links_itself', []);
    }
}
