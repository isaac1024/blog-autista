<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Domain;

use Cms\Relation\Domain\CmsSettingsPagesLinked;
use Shared\Domain\Event\EventId;
use Shared\Tests\Domain\ClockMother;

/**
 * Builds the event of the settings telling the pages their menus lead to, as
 * relations take it.
 */
final class CmsSettingsPagesLinkedMother
{
    public const string SETTINGS = '01a1c0de-5e77-7a5e-8a11-000000000001';

    public const string PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fb95';

    public const string OTHER_PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fb96';

    /**
     * @param list<string> $pageIds
     */
    public static function create(string $settings = self::SETTINGS, array $pageIds = [self::PAGE]): CmsSettingsPagesLinked
    {
        return new CmsSettingsPagesLinked($settings, $pageIds, EventId::random()->value(), ClockMother::now());
    }
}
