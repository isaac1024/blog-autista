<?php

declare(strict_types=1);

namespace Web\Tests\Settings\Application;

use PHPUnit\Framework\TestCase;
use Web\Settings\Application\SettingsSaver;
use Web\Settings\Domain\Settings;
use Web\Settings\Domain\SettingsRepository;
use Web\Tests\Settings\Domain\CmsSettingsPublishedMother as Event;

final class SettingsSaverTest extends TestCase
{
    public function testItPutsTheSettingsOnTheSiteTheFirstTime(): void
    {
        $event = Event::create();
        $repository = $this->createMock(SettingsRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::once())->method('save')->with(self::equalTo(Settings::publish($event)));

        (new SettingsSaver($event))($repository);
    }

    public function testItReplacesTheSettingsOnTheSite(): void
    {
        $later = Event::create(name: 'Otro nombre', defaultImageId: null, mainMenu: [], footerMenu: Event::MAIN_MENU);
        $repository = $this->createMock(SettingsRepository::class);
        $repository->method('find')->willReturn(Settings::publish(Event::create()));
        $repository->expects(self::once())->method('save')->with(self::equalTo(Settings::publish($later)));

        (new SettingsSaver($later))($repository);
    }

    /**
     * Only a later event is kept over a new one: two from the same moment
     * keep the one taken last.
     */
    public function testItTakesAVersionPublishedAtTheSameMoment(): void
    {
        $repository = $this->createMock(SettingsRepository::class);
        $repository->method('find')->willReturn(Settings::publish(Event::create()));
        $repository->expects(self::once())->method('save')->with(self::equalTo(Settings::publish(Event::create(name: 'Otro nombre'))));

        (new SettingsSaver(Event::create(name: 'Otro nombre')))($repository);
    }
}
