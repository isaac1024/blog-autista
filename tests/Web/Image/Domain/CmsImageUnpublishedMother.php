<?php

declare(strict_types=1);

namespace Web\Tests\Image\Domain;

use Web\Image\Domain\CmsImageUnpublished;

final class CmsImageUnpublishedMother
{
    public static function create(string $id = ImageIdMother::DEFAULT_VALUE, int $revision = 2): CmsImageUnpublished
    {
        return new CmsImageUnpublished($id, $revision);
    }
}
