<?php

declare(strict_types=1);

namespace Web\Analytics\Infrastructure\Persistence\Doctrine;

use Shared\Infrastructure\Persistence\Doctrine\UuidType;
use Web\Analytics\Domain\VisitId;

/**
 * Maps the VisitId value object to a native UUID column, so it can be the identifier.
 *
 * @extends UuidType<VisitId>
 */
final class VisitIdType extends UuidType
{
    #[\Override]
    protected function uuidClass(): string
    {
        return VisitId::class;
    }
}
