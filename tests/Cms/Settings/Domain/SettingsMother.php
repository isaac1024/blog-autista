<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Domain;

use Cms\Settings\Domain\AuthorId;
use Cms\Settings\Domain\ImageId;
use Cms\Settings\Domain\Menu;
use Cms\Settings\Domain\Settings;
use Cms\Settings\Domain\SettingsStatus;
use Cms\Settings\Domain\SettingsVersion;
use Cms\Settings\Domain\SiteName;
use Cms\Shared\Domain\PublicationTimeline;
use Shared\Tests\Domain\ClockMother;

/**
 * Builds Settings aggregates for tests, with sensible defaults that can be
 * overridden through named arguments:
 *
 *     SettingsMother::create()
 *     SettingsMother::create(SettingsMother::version(defaultImage: SettingsMother::IMAGE))
 *     SettingsMother::create(createdAt: '2026-10-01 09:00:00')
 *     SettingsMother::published()
 *     SettingsMother::published(SettingsMother::version(name: 'Otro nombre'), published: SettingsMother::version())
 *
 * Settings are drafts with the default name, no image and empty menus, and
 * are created and changed at ClockMother::NOW, unless a test says otherwise;
 * they are changed when they are created unless updatedAt says otherwise.
 */
final class SettingsMother
{
    public const string NAME = 'blog autista';

    public const string IMAGE = '01a1b801-ce58-723d-8234-b76d9105d001';

    public const string OTHER_IMAGE = '01a1b801-ce58-723d-8234-b76d9105d002';

    public const string PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fb95';

    public const string OTHER_PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fb96';

    public const string THIRD_PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fb97';

    /**
     * What an editor writes, as a version.
     *
     * @param list<array{page_id: string, label: ?string}> $mainMenu
     * @param list<array{page_id: string, label: ?string}> $footerMenu
     */
    public static function version(
        string $name = self::NAME,
        ?string $defaultImage = null,
        array $mainMenu = [],
        array $footerMenu = [],
        ?string $author = null,
    ): SettingsVersion {
        return new SettingsVersion(
            new SiteName($name),
            null === $defaultImage ? null : new ImageId($defaultImage),
            null === $author ? null : new AuthorId($author),
            Menu::fromArray($mainMenu),
            Menu::fromArray($footerMenu),
        );
    }

    /**
     * A menu entry, as menus store and events carry it.
     *
     * @return array{page_id: string, label: ?string}
     */
    public static function item(string $pageId = self::PAGE, ?string $label = null): array
    {
        return ['page_id' => $pageId, 'label' => $label];
    }

    public static function create(
        ?SettingsVersion $version = null,
        string $id = SettingsIdMother::DEFAULT_VALUE,
        string $createdAt = ClockMother::NOW,
        ?string $updatedAt = null,
    ): Settings {
        return new Settings(
            SettingsIdMother::create($id),
            $version ?? self::version(),
            SettingsStatus::Draft,
            null,
            new PublicationTimeline(ClockMother::now($createdAt), ClockMother::now($updatedAt ?? $createdAt), null, null),
            0,
        );
    }

    /**
     * On the site, with the version being edited unless a test gives the one
     * published apart, as settings with unpublished changes.
     */
    public static function published(
        ?SettingsVersion $version = null,
        ?SettingsVersion $published = null,
        string $id = SettingsIdMother::DEFAULT_VALUE,
        string $createdAt = ClockMother::NOW,
        ?string $updatedAt = null,
        int $publicationRevision = 1,
        string $firstPublishedAt = ClockMother::NOW,
        string $lastPublishedAt = ClockMother::NOW,
    ): Settings {
        $version ??= self::version();

        return new Settings(
            SettingsIdMother::create($id),
            $version,
            SettingsStatus::Published,
            $published ?? $version,
            new PublicationTimeline(ClockMother::now($createdAt), ClockMother::now($updatedAt ?? $createdAt), ClockMother::now($firstPublishedAt), ClockMother::now($lastPublishedAt)),
            $publicationRevision,
        );
    }
}
