<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

use Shared\Domain\DomainError;

/**
 * What the bio of an author can't be.
 */
final class InvalidAuthorBio extends DomainError
{
    public static function linksItself(): self
    {
        return new self('An author\'s bio cannot link to the author.', 'errors.author.bio.links_itself', []);
    }
}
