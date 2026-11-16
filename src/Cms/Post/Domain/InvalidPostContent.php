<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\DomainError;

/**
 * What the content of a post can't be.
 */
final class InvalidPostContent extends DomainError
{
    public static function linksItself(): self
    {
        return new self('A post cannot link to itself.', 'errors.post.content.links_itself', []);
    }
}
