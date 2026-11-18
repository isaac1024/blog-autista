<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Application;

use Cms\Settings\Application\SettingsNotFound;
use Cms\Settings\Application\SettingsPublisher;
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
use Shared\Tests\Domain\RecordingTransaction;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class SettingsPublisherTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;
    use PublishedEvents;
    private const string AUTHOR = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b02';

    private const string BEFORE = '2026-10-01 09:00:00';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    #[DataProvider('publishableSettings')]
    public function testItPutsTheSavedVersionOnTheSite(Settings $settings, Settings $expected): void
    {
        $repository = $this->createMock(SettingsRepository::class);
        $repository->method('find')->willReturn($settings);
        $repository->expects(self::once())->method('save')->with(self::equalTo($expected));

        (new SettingsPublisher())($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }

    /**
     * Only the name is required: the image and the menus may be missing.
     *
     * @return iterable<string, array{Settings, Settings}>
     */
    public static function publishableSettings(): iterable
    {
        $edited = Mother::version(name: 'Otro nombre', defaultImage: Mother::IMAGE, mainMenu: [Mother::item()]);

        yield 'a draft with only a name' => [Mother::create(createdAt: self::BEFORE), Mother::published(createdAt: self::BEFORE)];
        yield 'a draft with everything' => [Mother::create($edited, createdAt: self::BEFORE), Mother::published($edited, createdAt: self::BEFORE)];
        yield 'published settings with changes, keeping their first publication' => [
            Mother::published($edited, Mother::version(), createdAt: self::BEFORE, firstPublishedAt: self::BEFORE, lastPublishedAt: self::BEFORE),
            Mother::published($edited, createdAt: self::BEFORE, publicationRevision: 2, firstPublishedAt: self::BEFORE),
        ];
    }

    public function testItTellsTheSiteWhatToShow(): void
    {
        $told = [];
        $main = [Mother::item(Mother::PAGE, 'Sobre mí'), Mother::item(Mother::OTHER_PAGE)];
        $footer = [Mother::item(Mother::THIRD_PAGE)];
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(Mother::published(
            Mother::version('Otro nombre', Mother::IMAGE, $main, $footer, self::AUTHOR),
            Mother::version(defaultImage: Mother::IMAGE, mainMenu: $main, footerMenu: $footer),
        ));

        (new SettingsPublisher())($repository, $this->eventBusCollecting($told), new RecordingTransaction());

        self::assertSame([
            ['cms.settings.published', SettingsIdMother::DEFAULT_VALUE, [
                'name' => 'Otro nombre',
                'default_image_id' => Mother::IMAGE,
                'author_id' => self::AUTHOR,
                'main_menu' => $main,
                'footer_menu' => $footer,
                'revision' => 2,
            ]],
        ], $told);
    }

    /**
     * Once the site takes the new version, what only the old one used is no
     * longer in use.
     */
    public function testItTellsWhatTheSettingsUseOnceTheOldVersionLeavesTheSite(): void
    {
        $told = [];
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(Mother::published(
            Mother::version(),
            Mother::version(defaultImage: Mother::IMAGE, mainMenu: [Mother::item()]),
        ));

        (new SettingsPublisher())($repository, $this->eventBusCollecting($told), new RecordingTransaction());

        self::assertSame([
            ['cms.settings.images_used', SettingsIdMother::DEFAULT_VALUE, ['image_ids' => []]],
            ['cms.settings.pages_linked', SettingsIdMother::DEFAULT_VALUE, ['page_ids' => []]],
            ['cms.settings.published', SettingsIdMother::DEFAULT_VALUE, ['name' => Mother::NAME, 'default_image_id' => null, 'author_id' => null, 'main_menu' => [], 'footer_menu' => [], 'revision' => 2]],
        ], $told);
    }

    public function testItPublishesNothingWhenTheSettingsAreOnTheSiteAsTheyAre(): void
    {
        $repository = $this->createMock(SettingsRepository::class);
        $repository->method('find')->willReturn(Mother::published());
        $repository->expects(self::never())->method('save');
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::never())->method('publish');

        self::assertTells('errors.settings.publication.nothing_to_publish', [], static fn () => (new SettingsPublisher())($repository, $eventBus, new RecordingTransaction()));
    }

    public function testItFailsWhileTheSettingsDoNotExist(): void
    {
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(SettingsNotFound::create());

        (new SettingsPublisher())($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }
}
