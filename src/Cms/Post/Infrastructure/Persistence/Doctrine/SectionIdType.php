<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Persistence\Doctrine;

use Cms\Post\Domain\SectionId;
use Shared\Infrastructure\Persistence\Doctrine\UuidType;

/**
 * Maps the SectionId value object to a native UUID column: the section a post is filed under.
 *
 * @extends UuidType<SectionId>
 */
final class SectionIdType extends UuidType
{
    #[\Override]
    protected function uuidClass(): string
    {
        return SectionId::class;
    }
}
