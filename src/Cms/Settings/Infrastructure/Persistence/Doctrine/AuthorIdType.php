<?php

declare(strict_types=1);

namespace Cms\Settings\Infrastructure\Persistence\Doctrine;

use Cms\Settings\Domain\AuthorId;
use Shared\Infrastructure\Persistence\Doctrine\UuidType;

/**
 * Maps the AuthorId value object to a native UUID column: the identity of the author of the blog.
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
