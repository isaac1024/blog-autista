<?php

declare(strict_types=1);

namespace Web\Image\Infrastructure\Persistence\Doctrine;

use Shared\Infrastructure\Persistence\Doctrine\UuidType;
use Web\Image\Domain\ImageId;

/**
 * Maps the ImageId value object to a native UUID column, so it can be the identifier.
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
