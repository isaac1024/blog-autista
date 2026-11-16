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
        $repository->method('find')->willReturn(Settings::publish(Event::create(authorId: '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b02')));

        self::assertEquals(
            new SettingsResponse(Event::NAME, Event::IMAGE, '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b02', MenuEntries::read(Event::MAIN_MENU), MenuEntries::read(Event::FOOTER_MENU)),
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
