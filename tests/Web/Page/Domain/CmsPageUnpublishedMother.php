<?php

declare(strict_types=1);

namespace Web\Tests\Page\Domain;

use Shared\Domain\Event\EventId;
use Shared\Tests\Domain\ClockMother;
use Web\Page\Domain\CmsPageUnpublished;

/**
 * Builds the event the CMS sends the site when it unpublishes a page:
 *
 *     CmsPageUnpublishedMother::create()
 *     CmsPageUnpublishedMother::create(occurredOn: '2026-11-08 10:00:00')
 *
 * It happens at ClockMother::NOW unless a test says otherwise.
 */
final class CmsPageUnpublishedMother
{
    public static function create(string $id = PageIdMother::DEFAULT_VALUE, string $occurredOn = ClockMother::NOW, int $revision = 2): CmsPageUnpublished
    {
        return new CmsPageUnpublished($id, EventId::random()->value(), ClockMother::now($occurredOn), $revision);
    }
}
