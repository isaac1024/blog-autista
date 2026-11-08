<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Application;

use Cms\Settings\Application\SettingsCreator;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Tests\Settings\Domain\PublishedEvents;
use Cms\Tests\Settings\Domain\SettingsIdMother;
use Cms\Tests\Settings\Domain\SettingsMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class SettingsCreatorTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;
    use PublishedEvents;

    #[\Override]
    protected function setUp(): void
    {
        // The domain dates what happens, so it is dated as the settings compared with.
        self::mockTime(ClockMother::now());
    }

    #[DataProvider('validNames')]
    public function testItCreatesTheSettingsAsADraftWithTheName(string $name, string $expected): void
    {
        $repository = $this->createMock(SettingsRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::once())->method('save')->with(self::equalTo(
            SettingsMother::create(SettingsMother::version(name: $expected)),
        ));

        (new SettingsCreator(SettingsIdMother::DEFAULT_VALUE, $name))($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validNames(): iterable
    {
        yield 'a name' => ['blog autista', 'blog autista'];
        yield 'a name with its whitespace collapsed' => ["  blog \t autista ", 'blog autista'];
        yield 'shortest name' => ['ab', 'ab'];
        // Same length in characters, but 120 bytes: the limit counts characters.
        yield 'longest name with multibyte characters' => [str_repeat('á', 60), str_repeat('á', 60)];
    }

    /**
     * New settings use no image nor page, so they tell nothing.
     */
    public function testItTellsNothing(): void
    {
        $told = [];
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn(null);

        (new SettingsCreator(SettingsIdMother::DEFAULT_VALUE, SettingsMother::NAME))($repository, $this->eventBusCollecting($told));

        self::assertSame([], $told);
    }

    public function testItFailsAndKeepsTheSettingsWhenTheyAlreadyExist(): void
    {
        $repository = $this->createMock(SettingsRepository::class);
        $repository->method('find')->willReturn(SettingsMother::create());
        $repository->expects(self::never())->method('save');

        self::assertTells('errors.settings.already_exist', [], static fn () => (new SettingsCreator(SettingsIdMother::random()->value(), 'Otro nombre'))(
            $repository,
            self::createStub(EventBus::class),
        ));
    }

    /**
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('invalidNames')]
    public function testItCreatesNothingWhenTheNameIsInvalid(string $name, string $key, array $parameters): void
    {
        $repository = $this->createMock(SettingsRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('save');

        self::assertTells($key, $parameters, static fn () => (new SettingsCreator(SettingsIdMother::DEFAULT_VALUE, $name))(
            $repository,
            self::createStub(EventBus::class),
        ));
    }

    /**
     * @return iterable<string, array{string, string, array<string, int|string>}>
     */
    public static function invalidNames(): iterable
    {
        yield 'blank name' => [" \t ", 'errors.settings.name.too_short', ['%min%' => 2]];
        yield 'name one character too short' => ['a', 'errors.settings.name.too_short', ['%min%' => 2]];
        // Two bytes, but one character: the limit counts characters.
        yield 'name of one multibyte character' => ['á', 'errors.settings.name.too_short', ['%min%' => 2]];
        yield 'name one character too long' => [str_repeat('a', 61), 'errors.settings.name.too_long', ['%max%' => 60]];
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(SettingsRepository::class);
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidUuid::withValue('settings-id'));

        (new SettingsCreator('settings-id', SettingsMother::NAME))($repository, self::createStub(EventBus::class));
    }
}
