<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Application;

use Cms\Settings\Application\SettingsOnSiteFinder;
use Cms\Settings\Application\SettingsOnSiteResponse;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Tests\Settings\Domain\SettingsMother;
use PHPUnit\Framework\TestCase;

final class SettingsOnSiteFinderTest extends TestCase
{
    /**
     * The site shows the version last published, not the one edited since.
     */
    public function testItGivesTheSettingsAsTheSiteShowsThem(): void
    {
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(SettingsMother::published(
            version: SettingsMother::version(name: 'Nombre editado'),
            published: SettingsMother::version(
                name: 'Nombre publicado',
                defaultImage: SettingsMother::IMAGE,
                mainMenu: [SettingsMother::item(SettingsMother::PAGE, 'Sobre mí')],
                footerMenu: [SettingsMother::item(SettingsMother::OTHER_PAGE)],
            ),
        ));

        self::assertEquals(
            new SettingsOnSiteResponse(
                'Nombre publicado',
                SettingsMother::IMAGE,
                [['page_id' => SettingsMother::PAGE, 'label' => 'Sobre mí']],
                [['page_id' => SettingsMother::OTHER_PAGE, 'label' => null]],
            ),
            new SettingsOnSiteFinder()($repository),
        );
    }

    public function testItFindsNoneWhileTheSettingsAreNotPublished(): void
    {
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(SettingsMother::create());

        self::assertNull(new SettingsOnSiteFinder()($repository));
    }

    public function testItFindsNoneWhileThereAreNoSettings(): void
    {
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(null);

        self::assertNull(new SettingsOnSiteFinder()($repository));
    }
}
