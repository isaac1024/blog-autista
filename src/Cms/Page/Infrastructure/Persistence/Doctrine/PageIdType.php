<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Persistence\Doctrine;

use Cms\Page\Domain\PageId;
use Shared\Infrastructure\Persistence\Doctrine\UuidType;

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
