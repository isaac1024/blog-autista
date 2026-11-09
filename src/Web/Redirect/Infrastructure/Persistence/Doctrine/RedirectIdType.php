<?php

declare(strict_types=1);

namespace Web\Redirect\Infrastructure\Persistence\Doctrine;

use Shared\Infrastructure\Persistence\Doctrine\UuidType;
use Web\Redirect\Domain\RedirectId;

/**
 * Maps the RedirectId value object to a native UUID column, so it can be the identifier.
 *
 * @extends UuidType<RedirectId>
 */
final class RedirectIdType extends UuidType
{
    #[\Override]
    protected function uuidClass(): string
    {
        return RedirectId::class;
    }
}
