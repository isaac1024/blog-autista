<?php

declare(strict_types=1);

namespace Web\Tests\Image\Domain;

use Shared\Domain\Event\EventId;
use Shared\Tests\Domain\ClockMother;
use Web\Image\Domain\CmsImageUnpublished;

final class CmsImageUnpublishedMother
{
    public static function create(string $id = ImageIdMother::DEFAULT_VALUE, string $occurredOn = ClockMother::NOW): CmsImageUnpublished
    {
        return new CmsImageUnpublished($id, EventId::random()->value(), ClockMother::now($occurredOn));
    }
}
