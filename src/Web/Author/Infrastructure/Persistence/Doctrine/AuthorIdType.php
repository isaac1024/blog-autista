<?php

declare(strict_types=1);

namespace Web\Author\Infrastructure\Persistence\Doctrine;

use Shared\Infrastructure\Persistence\Doctrine\UuidType;
use Web\Author\Domain\AuthorId;

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
