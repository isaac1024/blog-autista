<?php

declare(strict_types=1);

namespace Web\Page\Infrastructure\Persistence\Doctrine;

use Shared\Infrastructure\Persistence\Doctrine\UuidType;
use Web\Page\Domain\PageId;

/**
 * Maps the PageId value object to a native UUID column, so it can be the identifier.
 *
 * @extends UuidType<PageId>
 */
final class PageIdType extends UuidType
{
    #[\Override]
    protected function uuidClass(): string
    {
        return PageId::class;
    }
}
