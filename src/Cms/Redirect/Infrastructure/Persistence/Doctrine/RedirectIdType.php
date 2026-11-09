<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Persistence\Doctrine;

use Cms\Redirect\Domain\RedirectId;
use Shared\Infrastructure\Persistence\Doctrine\UuidType;

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
