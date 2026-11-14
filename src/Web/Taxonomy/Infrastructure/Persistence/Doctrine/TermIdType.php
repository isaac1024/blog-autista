<?php

declare(strict_types=1);

namespace Web\Taxonomy\Infrastructure\Persistence\Doctrine;

use Shared\Infrastructure\Persistence\Doctrine\UuidType;
use Web\Taxonomy\Domain\TermId;

/**
 * Maps the TermId value object to a native UUID column, so it can be the identifier.
 *
 * @extends UuidType<TermId>
 */
final class TermIdType extends UuidType
{
    #[\Override]
    protected function uuidClass(): string
    {
        return TermId::class;
    }
}
