<?php

declare(strict_types=1);

namespace Web\External\Infrastructure\Persistence\Doctrine;

use Shared\Infrastructure\Persistence\Doctrine\UuidType;
use Web\External\Domain\ExternalResourceId;

/**
 * Maps the ExternalResourceId value object to a native UUID column, so it can be the identifier.
 *
 * @extends UuidType<ExternalResourceId>
 */
final class ExternalResourceIdType extends UuidType
{
    #[\Override]
    protected function uuidClass(): string
    {
        return ExternalResourceId::class;
    }
}
