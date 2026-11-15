<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Persistence\Doctrine;

use Cms\External\Domain\ExternalResourceId;
use Shared\Infrastructure\Persistence\Doctrine\UuidType;

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
