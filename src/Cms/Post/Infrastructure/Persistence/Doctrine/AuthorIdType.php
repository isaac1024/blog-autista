<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Persistence\Doctrine;

use Cms\Post\Domain\AuthorId;
use Shared\Infrastructure\Persistence\Doctrine\UuidType;

/**
 * Maps the AuthorId value object to a native UUID column: the author a post is signed by.
 *
 * @extends UuidType<AuthorId>
 */
final class AuthorIdType extends UuidType
{
    #[\Override]
    protected function uuidClass(): string
    {
        return AuthorId::class;
    }
}
