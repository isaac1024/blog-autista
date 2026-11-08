<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Application;

use Cms\Settings\Application\SettingsChangesDiscarder;
use Cms\Settings\Application\SettingsNotFound;
use Cms\Settings\Domain\Settings;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Tests\Settings\Domain\PublishedEvents;
use Cms\Tests\Settings\Domain\SettingsIdMother;
use Cms\Tests\Settings\Domain\SettingsMother as Mother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class SettingsChangesDiscarderTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;
    use PublishedEvents;

    private const string BEFORE = '2026-10-01 09:00:00';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItBringsTheEditedVersionBackToTheOneOnTheSite(): void
    {
        $repository = $this->createMock(SettingsRepository::class);
        $repository->method('find')->willReturn(Mother::published(Mother::version(name: 'Otro nombre'), Mother::version(), createdAt: self::BEFORE));
        $repository->expects(self::once())->method('save')->with(self::equalTo(
            Mother::published(createdAt: self::BEFORE, updatedAt: ClockMother::NOW),
        ));

        (new SettingsChangesDiscarder())($repository, self::createStub(EventBus::class));
    }

    /**
     * What only the changes used is no longer in use; the site hears nothing.
     */
    public function testItTellsOnlyWhatTheSettingsUseNow(): void
    {
        $told = [];
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(Mother::published(
            Mother::version(defaultImage: Mother::IMAGE, mainMenu: [Mother::item(Mother::PAGE), Mother::item(Mother::OTHER_PAGE)]),
            Mother::version(mainMenu: [Mother::item(Mother::PAGE)]),
        ));

        (new SettingsChangesDiscarder())($repository, $this->eventBusCollecting($told));

        self::assertSame([
            ['cms.settings.images_used', SettingsIdMother::DEFAULT_VALUE, ['image_ids' => []]],
            ['cms.settings.pages_linked', SettingsIdMother::DEFAULT_VALUE, ['page_ids' => [Mother::PAGE]]],
        ], $told);
    }

    #[DataProvider('settingsWithoutChanges')]
    public function testItDiscardsNothingWithoutUnpublishedChanges(Settings $settings): void
    {
        $repository = $this->createMock(SettingsRepository::class);
        $repository->method('find')->willReturn($settings);
        $repository->expects(self::never())->method('save');

        self::assertTells('errors.settings.publication.no_changes_to_discard', [], static fn () => (new SettingsChangesDiscarder())(
            $repository,
            self::createStub(EventBus::class),
        ));
    }

    /**
     * @return iterable<string, array{Settings}>
     */
    public static function settingsWithoutChanges(): iterable
    {
        yield 'a draft' => [Mother::create(Mother::version(name: 'Otro nombre'))];
        yield 'published settings as they are on the site' => [Mother::published()];
    }

    public function testItFailsWhileTheSettingsDoNotExist(): void
    {
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(SettingsNotFound::create());

        (new SettingsChangesDiscarder())($repository, self::createStub(EventBus::class));
    }
}
