<?php

declare(strict_types=1);

namespace Web\Tests\Settings\Domain;

use Shared\Domain\Event\EventId;
use Shared\Tests\Domain\ClockMother;
use Web\Settings\Domain\CmsSettingsPublished;

/**
 * Builds the event of the CMS publishing its settings, as the site takes it:
 *
 *     CmsSettingsPublishedMother::create()
 *     CmsSettingsPublishedMother::create(name: 'Otro nombre', occurredOn: '2026-11-07 11:00:00')
 *     CmsSettingsPublishedMother::body()
 */
final class CmsSettingsPublishedMother
{
    public const string ID = '01a1c0de-5e77-7a5e-8a11-000000000001';

    public const string NAME = 'blog autista';

    public const string IMAGE = '01a1b801-ce58-723d-8234-b76d9105d001';

    public const string PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fb95';

    public const string OTHER_PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fb96';

    /**
     * The menus the CMS sends unless a test says otherwise.
     */
    public const array MAIN_MENU = [['page_id' => self::PAGE, 'label' => 'Sobre mí']];

    public const array FOOTER_MENU = [['page_id' => self::OTHER_PAGE, 'label' => null]];

    /**
     * The body of the event as it travels from the CMS.
     *
     * @param list<array{page_id: string, label: ?string}> $mainMenu
     * @param list<array{page_id: string, label: ?string}> $footerMenu
     *
     * @return array<string, mixed>
     */
    public static function body(
        string $name = self::NAME,
        ?string $defaultImageId = self::IMAGE,
        array $mainMenu = self::MAIN_MENU,
        array $footerMenu = self::FOOTER_MENU,
    ): array {
        return ['name' => $name, 'default_image_id' => $defaultImageId, 'main_menu' => $mainMenu, 'footer_menu' => $footerMenu];
    }

    /**
     * @param list<array{page_id: string, label: ?string}> $mainMenu
     * @param list<array{page_id: string, label: ?string}> $footerMenu
     */
    public static function create(
        string $name = self::NAME,
        ?string $defaultImageId = self::IMAGE,
        array $mainMenu = self::MAIN_MENU,
        array $footerMenu = self::FOOTER_MENU,
        string $occurredOn = ClockMother::NOW,
        string $id = self::ID,
        int $revision = 1,
    ): CmsSettingsPublished {
        return new CmsSettingsPublished($id, $name, $defaultImageId, $mainMenu, $footerMenu, EventId::random()->value(), ClockMother::now($occurredOn), $revision);
    }
}
