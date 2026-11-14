<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Persistence\Doctrine;

use Cms\Taxonomy\Domain\TermId;
use Shared\Infrastructure\Persistence\Doctrine\UuidType;

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
