<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Persistence\Doctrine;

use Cms\Post\Domain\ImageId;
use Shared\Infrastructure\Persistence\Doctrine\UuidType;

/**
 * Maps the ImageId value object to a native UUID column: the image a post opens with.
 *
 * @extends UuidType<ImageId>
 */
final class ImageIdType extends UuidType
{
    #[\Override]
    protected function uuidClass(): string
    {
        return ImageId::class;
    }
}
