<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Persistence\Doctrine;

use Cms\Post\Domain\PostId;
use Shared\Infrastructure\Persistence\Doctrine\UuidType;

/**
 * Maps the PostId value object to a native UUID column, so it can be the identifier.
 *
 * @extends UuidType<PostId>
 */
final class PostIdType extends UuidType
{
    #[\Override]
    protected function uuidClass(): string
    {
        return PostId::class;
    }
}
