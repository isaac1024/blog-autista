<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Persistence\Doctrine;

use Cms\Relation\Domain\RelationId;
use Shared\Infrastructure\Persistence\Doctrine\UuidType;

/**
 * Maps the RelationId value object to a native UUID column, so it can be the identifier.
 *
 * @extends UuidType<RelationId>
 */
final class RelationIdType extends UuidType
{
    #[\Override]
    protected function uuidClass(): string
    {
        return RelationId::class;
    }
}
