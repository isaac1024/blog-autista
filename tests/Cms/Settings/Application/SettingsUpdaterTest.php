<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Application;

use Cms\Settings\Application\SettingsNotFound;
use Cms\Settings\Application\SettingsUpdater;
use Cms\Settings\Domain\Settings;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Settings\Domain\SettingsVersion;
use Cms\Tests\Settings\Domain\PublishedEvents;
use Cms\Tests\Settings\Domain\SettingsIdMother;
use Cms\Tests\Settings\Domain\SettingsMother as Mother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class SettingsUpdaterTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;
    use PublishedEvents;
    private const string POST = '01a1e2a0-0000-7000-8000-0000000000d1';

    private const string TERM = '01a1e2a0-0000-7000-8000-0000000000d2';

    private const string AUTHOR = '01a1e2a0-0000-7000-8000-0000000000d3';

    private const string EXTERNAL = '01a1e2a0-0000-7000-8000-0000000000d4';

    /**
     * When the settings edited here were created and last changed.
     */
    private const string BEFORE = '2026-10-01 09:00:00';

    #[\Override]
    protected function setUp(): void
    {
        // The domain dates what happens, so it is dated as the settings compared with.
        self::mockTime(ClockMother::now());
    }

    /**
     * @param array<array-key, mixed> $mainMenu
     * @param array<array-key, mixed> $footerMenu
     */
    #[DataProvider('validEdits')]
    public function testItSavesTheEditedSettingsAndWhenTheyChanged(string $name, string $image, array $mainMenu, array $footerMenu, SettingsVersion $expected): void
    {
        $repository = $this->createMock(SettingsRepository::class);
        $repository->method('find')->willReturn(Mother::create(createdAt: self::BEFORE));
        $repository->expects(self::once())->method('save')->with(self::equalTo(
            Mother::create($expected, createdAt: self::BEFORE, updatedAt: ClockMother::NOW),
        ));

        (new SettingsUpdater($name, $image, $mainMenu, $footerMenu))($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{string, string, array<array-key, mixed>, array<array-key, mixed>, SettingsVersion}>
     */
    public static function validEdits(): iterable
    {
        $main = [Mother::item(Mother::PAGE, 'Sobre mí'), Mother::item(Mother::OTHER_PAGE)];
        $footer = [Mother::item(Mother::THIRD_PAGE)];
        $full = array_map(
            static fn (int $page): array => Mother::item(\sprintf('01a19e72-b07f-7d78-889c-fa4a14a4fc%02d', $page)),
            range(1, 8),
        );

        yield 'everything' => ['Otro nombre', Mother::IMAGE, $main, $footer, Mother::version('Otro nombre', Mother::IMAGE, $main, $footer)];
        yield 'a name with its whitespace collapsed' => ["  Otro \t nombre ", '', [], [], Mother::version('Otro nombre')];
        yield 'no image when it is left blank' => ['Otro nombre', '', $main, [], Mother::version('Otro nombre', null, $main)];
        yield 'labels with their whitespace collapsed' => [
            Mother::NAME,
            '',
            [['page_id' => Mother::PAGE, 'label' => "  Sobre \t mí "]],
            [],
            Mother::version(mainMenu: [Mother::item(Mother::PAGE, 'Sobre mí')]),
        ];
        yield 'no label when it is blank or left out' => [
            Mother::NAME,
            '',
            [['page_id' => Mother::PAGE, 'label' => ' '], ['page_id' => Mother::OTHER_PAGE]],
            [],
            Mother::version(mainMenu: [Mother::item(Mother::PAGE), Mother::item(Mother::OTHER_PAGE)]),
        ];
        yield 'the same page in both menus' => [Mother::NAME, '', $footer, $footer, Mother::version(mainMenu: $footer, footerMenu: $footer)];
        yield 'menus at their fullest' => [Mother::NAME, '', $full, $full, Mother::version(mainMenu: $full, footerMenu: $full)];
        yield 'longest label with multibyte characters' => [
            Mother::NAME,
            '',
            [Mother::item(Mother::PAGE, str_repeat('á', 40))],
            [],
            Mother::version(mainMenu: [Mother::item(Mother::PAGE, str_repeat('á', 40))]),
        ];
    }

    public function testItKeepsWhenTheSettingsLastChangedWhenNothingChanges(): void
    {
        $main = [Mother::item(Mother::PAGE, 'Sobre mí')];
        $settings = static fn (): Settings => Mother::create(Mother::version(defaultImage: Mother::IMAGE, mainMenu: $main), createdAt: self::BEFORE);
        $repository = $this->createMock(SettingsRepository::class);
        $repository->method('find')->willReturn($settings());
        $repository->expects(self::once())->method('save')->with(self::equalTo($settings()));

        (new SettingsUpdater(' '.Mother::NAME.' ', Mother::IMAGE, [['page_id' => Mother::PAGE, 'label' => ' Sobre mí ']], []))(
            $repository,
            self::createStub(EventBus::class),
        );
    }

    /**
     * A page in both menus is used once, and the pages come sorted.
     */
    public function testItTellsTheImageAndThePagesItUsesNow(): void
    {
        $told = [];
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(Mother::create());

        (new SettingsUpdater(
            Mother::NAME,
            Mother::IMAGE,
            [Mother::item(Mother::OTHER_PAGE), Mother::item(Mother::PAGE)],
            [Mother::item(Mother::PAGE)],
        ))($repository, $this->eventBusCollecting($told));

        self::assertSame([
            ['cms.settings.images_used', SettingsIdMother::DEFAULT_VALUE, ['image_ids' => [Mother::IMAGE]]],
            ['cms.settings.pages_linked', SettingsIdMother::DEFAULT_VALUE, ['page_ids' => [Mother::PAGE, Mother::OTHER_PAGE]]],
        ], $told);
    }

    /**
     * Besides pages, a menu leads to posts, sections and tags, authors and
     * external resources, each kind told apart from the pages.
     */
    public function testItTellsTheOtherContentsTheMenusLeadTo(): void
    {
        $told = [];
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(Mother::create());

        (new SettingsUpdater(Mother::NAME, '', [
            ['external_id' => self::EXTERNAL, 'label' => 'Un vídeo'],
            ['post_id' => self::POST, 'label' => null],
        ], [
            ['term_id' => self::TERM, 'label' => null],
            ['author_id' => self::AUTHOR, 'label' => null],
        ]))($repository, $this->eventBusCollecting($told));

        self::assertSame([
            ['cms.settings.contents_linked', SettingsIdMother::DEFAULT_VALUE, ['post_ids' => [self::POST], 'term_ids' => [self::TERM], 'author_ids' => [self::AUTHOR], 'external_ids' => [self::EXTERNAL]]],
        ], $told);
    }

    public function testItTellsItUsesNoImageNorPageOnceTheyAreRemoved(): void
    {
        $told = [];
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(Mother::create(Mother::version(defaultImage: Mother::IMAGE, footerMenu: [Mother::item()])));

        (new SettingsUpdater(Mother::NAME, '', [], []))($repository, $this->eventBusCollecting($told));

        self::assertSame([
            ['cms.settings.images_used', SettingsIdMother::DEFAULT_VALUE, ['image_ids' => []]],
            ['cms.settings.pages_linked', SettingsIdMother::DEFAULT_VALUE, ['page_ids' => []]],
        ], $told);
    }

    /**
     * Renaming, relabelling or reordering uses the same image and pages.
     */
    public function testItTellsNothingWhenTheImageAndThePagesUsedStayTheSame(): void
    {
        $told = [];
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(Mother::create(Mother::version(
            defaultImage: Mother::IMAGE,
            mainMenu: [Mother::item(Mother::PAGE), Mother::item(Mother::OTHER_PAGE)],
        )));

        (new SettingsUpdater('Otro nombre', Mother::IMAGE, [Mother::item(Mother::OTHER_PAGE, 'Otra'), Mother::item(Mother::PAGE)], []))(
            $repository,
            $this->eventBusCollecting($told),
        );

        self::assertSame([], $told);
    }

    /**
     * The site still uses the version published, so what it uses stays in use
     * until the settings are published again.
     */
    public function testItKeepsTellingWhatTheVersionOnTheSiteUses(): void
    {
        $told = [];
        $published = Mother::version(defaultImage: Mother::IMAGE, mainMenu: [Mother::item(Mother::PAGE)]);
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(Mother::published($published));

        (new SettingsUpdater(Mother::NAME, Mother::OTHER_IMAGE, [], [Mother::item(Mother::PAGE)]))($repository, $this->eventBusCollecting($told));

        self::assertSame([
            ['cms.settings.images_used', SettingsIdMother::DEFAULT_VALUE, ['image_ids' => [Mother::IMAGE, Mother::OTHER_IMAGE]]],
        ], $told);
    }

    /**
     * Using again what the version on the site uses changes nothing in use.
     */
    public function testItTellsNothingWhenTheEditUsesAgainWhatTheSiteUses(): void
    {
        $told = [];
        $published = Mother::version(defaultImage: Mother::IMAGE, mainMenu: [Mother::item()]);
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(Mother::published(Mother::version(), $published));

        (new SettingsUpdater(Mother::NAME, Mother::IMAGE, [Mother::item()], []))($repository, $this->eventBusCollecting($told));

        self::assertSame([], $told);
    }

    /**
     * What the error tells is spelt out, not built by the code that throws
     * it, so a test catches its key or parameters changing.
     *
     * @param array<array-key, mixed>   $mainMenu
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('invalidEdits')]
    public function testItSavesNothingWhenTheEditIsInvalid(string $name, string $image, array $mainMenu, string $key, array $parameters): void
    {
        $repository = $this->createMock(SettingsRepository::class);
        $repository->method('find')->willReturn(Mother::create());
        $repository->expects(self::never())->method('save');
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::never())->method('publish');

        self::assertTells($key, $parameters, static fn () => (new SettingsUpdater($name, $image, $mainMenu, []))($repository, $eventBus));
    }

    /**
     * @return iterable<string, array{string, string, array<array-key, mixed>, string, array<string, int|string>}>
     */
    public static function invalidEdits(): iterable
    {
        $name = Mother::NAME;
        $nine = array_map(
            static fn (int $page): array => Mother::item(\sprintf('01a19e72-b07f-7d78-889c-fa4a14a4fc%02d', $page)),
            range(1, 9),
        );

        yield 'blank name' => [' ', '', [], 'errors.settings.name.too_short', ['%min%' => 2]];
        yield 'name one character too long' => [str_repeat('a', 61), '', [], 'errors.settings.name.too_long', ['%max%' => 60]];
        yield 'image that is not an id' => [$name, 'rectangulo', [], 'errors.uuid.invalid', ['%value%' => 'rectangulo']];
        yield 'menu one entry too long' => [$name, '', $nine, 'errors.settings.menu.too_many_items', ['%max%' => 8]];
        yield 'menu with a page twice' => [$name, '', [Mother::item(), Mother::item(label: 'Otra vez')], 'errors.settings.menu.repeated_entry', []];
        yield 'label one character too long' => [$name, '', [Mother::item(label: str_repeat('a', 41))], 'errors.settings.menu.label_too_long', ['%max%' => 40]];
        yield 'entry without a page' => [$name, '', [['label' => 'Sobre mí']], 'errors.settings.menu.malformed', []];
        yield 'entry that is not a list' => [$name, '', ['Sobre mí'], 'errors.settings.menu.malformed', []];
        yield 'label that is not text' => [$name, '', [['page_id' => Mother::PAGE, 'label' => ['Sobre mí']]], 'errors.settings.menu.malformed', []];
        yield 'page that is not an id' => [$name, '', [['page_id' => 'sobre-mi', 'label' => null]], 'errors.settings.menu.malformed', []];
        yield 'entry leading to two contents' => [$name, '', [['page_id' => Mother::PAGE, 'post_id' => Mother::OTHER_PAGE, 'label' => null]], 'errors.settings.menu.malformed', []];
        yield 'entry leading nowhere' => [$name, '', [['label' => 'Sobre mí']], 'errors.settings.menu.malformed', []];
        yield 'entry with a label that is no text' => [$name, '', [['page_id' => Mother::PAGE, 'label' => ['Sobre mí']]], 'errors.settings.menu.malformed', []];
        yield 'a post twice' => [$name, '', [['post_id' => Mother::PAGE, 'label' => null], ['post_id' => Mother::PAGE, 'label' => 'Otra vez']], 'errors.settings.menu.repeated_entry', []];
    }

    public function testItFailsWhileTheSettingsDoNotExist(): void
    {
        $repository = $this->createMock(SettingsRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(SettingsNotFound::create());

        (new SettingsUpdater(Mother::NAME, '', [], []))($repository, self::createStub(EventBus::class));
    }
}
