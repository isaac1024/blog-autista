<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Persistence\Doctrine;

use Cms\Author\Domain\AuthorId;
use Shared\Infrastructure\Persistence\Doctrine\UuidType;

/**
 * Maps the AuthorId value object to a native UUID column, so it can be the identifier.
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
