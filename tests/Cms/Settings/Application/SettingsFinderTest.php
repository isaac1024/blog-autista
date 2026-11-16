<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Application;

use Cms\Settings\Application\SettingsFinder;
use Cms\Settings\Application\SettingsNotFound;
use Cms\Settings\Application\SettingsResponse;
use Cms\Settings\Domain\Menu;
use Cms\Settings\Domain\Settings;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Tests\Settings\Domain\SettingsMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Tests\Domain\ClockMother;

final class SettingsFinderTest extends TestCase
{
    #[DataProvider('settings')]
    public function testItFindsTheSettingsAsTheCmsShowsThem(Settings $settings, SettingsResponse $expected): void
    {
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn($settings);

        self::assertEquals($expected, (new SettingsFinder())($repository));
    }

    /**
     * @return iterable<string, array{Settings, SettingsResponse}>
     */
    public static function settings(): iterable
    {
        $menu = [SettingsMother::item(SettingsMother::PAGE, 'Sobre mí'), SettingsMother::item(SettingsMother::OTHER_PAGE)];
        $footer = [SettingsMother::item(SettingsMother::THIRD_PAGE)];
        $version = SettingsMother::version(defaultImage: SettingsMother::IMAGE, mainMenu: $menu, footerMenu: $footer);
        $created = ClockMother::now('2026-10-01 09:00:00');

        yield 'a draft' => [
            SettingsMother::create($version, createdAt: '2026-10-01 09:00:00'),
            new SettingsResponse(SettingsMother::NAME, SettingsMother::IMAGE, null, Menu::fromArray($menu)->view(), Menu::fromArray($footer)->view(), $created, $created, 'draft', false, true),
        ];
        yield 'published settings as they are on the site' => [
            SettingsMother::published(SettingsMother::version(), createdAt: '2026-10-01 09:00:00', updatedAt: ClockMother::NOW),
            new SettingsResponse(SettingsMother::NAME, null, null, [], [], $created, ClockMother::now(), 'published', false, false),
        ];
        yield 'published settings with changes' => [
            SettingsMother::published(SettingsMother::version(name: 'Otro nombre'), SettingsMother::version(), createdAt: '2026-10-01 09:00:00'),
            new SettingsResponse('Otro nombre', null, null, [], [], $created, $created, 'published', true, true),
        ];
    }

    public function testItFailsWhileTheSettingsDoNotExist(): void
    {
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(SettingsNotFound::create());

        (new SettingsFinder())($repository);
    }
}
