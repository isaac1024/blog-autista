<?php

declare(strict_types=1);

namespace Cms\Settings\Infrastructure\Persistence\Doctrine;

use Cms\Settings\Domain\ImageId;
use Shared\Infrastructure\Persistence\Doctrine\UuidType;

/**
 * Maps the ImageId value object to a native UUID column: the identity of the default image of the settings.
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
