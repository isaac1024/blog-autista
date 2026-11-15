<?php

declare(strict_types=1);

namespace Web\Tests\Settings\Application;

use PHPUnit\Framework\TestCase;
use Web\Settings\Application\SettingsOnSiteFinder;
use Web\Settings\Application\SettingsResponse;
use Web\Settings\Domain\MenuEntries;
use Web\Settings\Domain\Settings;
use Web\Settings\Domain\SettingsRepository;
use Web\Tests\Settings\Domain\CmsSettingsPublishedMother as Event;

final class SettingsOnSiteFinderTest extends TestCase
{
    public function testItFindsTheSettingsOnTheSite(): void
    {
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(Settings::publish(Event::create()));

        self::assertEquals(
            new SettingsResponse(Event::NAME, Event::IMAGE, MenuEntries::read(Event::MAIN_MENU), MenuEntries::read(Event::FOOTER_MENU)),
            (new SettingsOnSiteFinder())($repository),
        );
    }

    public function testItFindsNothingUntilTheCmsPublishesThem(): void
    {
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(null);

        self::assertNull((new SettingsOnSiteFinder())($repository));
    }
}
