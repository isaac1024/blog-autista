<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageCreator;
use Cms\Page\Domain\InvalidPageTitle;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageSlugAlreadyTaken;
use Cms\Page\Domain\PageTitle;
use Cms\Shared\Domain\InvalidSlug;
use Cms\Shared\Domain\Slug;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Domain\SlugMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RecordingTransaction;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PageCreatorTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    #[\Override]
    protected function setUp(): void
    {
        // The domain dates what happens, so it is dated as the pages compared with.
        self::mockTime(ClockMother::now());
    }

    #[DataProvider('validInputs')]
    public function testItPersistsThePageBuiltFromValidInput(string $id, string $title, Page $expected): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo($expected));

        (new PageCreator($id, $title))($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }

    /**
     * @return iterable<string, array{string, string, Page}>
     */
    public static function validInputs(): iterable
    {
        $id = PageIdMother::DEFAULT_VALUE;

        yield 'plain title' => [$id, 'Posts', PageMother::create(id: $id, title: 'Posts', slug: 'posts')];
        yield 'surrounding whitespace is trimmed' => [$id, '  Sobre mí  ', PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi')];
        yield 'stop words are left out of the slug' => [
            $id,
            'Cómo organizar la semana',
            PageMother::create(id: $id, title: 'Cómo organizar la semana', slug: 'como-organizar-semana'),
        ];
        yield 'shortest title and slug' => [$id, 'FAQ', PageMother::create(id: $id, title: 'FAQ', slug: 'faq')];

        // 76 letters plus " y" twelve times: exactly 100 characters, and the
        // stop word "y" keeps the slug within its own maximum.
        $longestTitle = str_repeat('a', 76).' '.rtrim(str_repeat('y ', 12));
        yield 'longest title' => [$id, $longestTitle, PageMother::create(id: $id, title: $longestTitle, slug: str_repeat('a', 76))];

        // Same length in characters, but 176 bytes: the limit counts characters.
        $longestMultibyteTitle = str_repeat('á', 76).' '.rtrim(str_repeat('y ', 12));
        yield 'longest title with multibyte characters' => [
            $id,
            $longestMultibyteTitle,
            PageMother::create(id: $id, title: $longestMultibyteTitle, slug: str_repeat('a', 76)),
        ];

        yield 'longest slug' => [$id, str_repeat('a', 80), PageMother::create(id: $id, title: str_repeat('a', 80), slug: str_repeat('a', 80))];
    }

    #[DataProvider('invalidInputs')]
    public function testItPersistsNothingWhenTheInputIsInvalid(string $id, string $title, DomainError $error): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject($error);

        (new PageCreator($id, $title))($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }

    /**
     * @return iterable<string, array{string, string, DomainError}>
     */
    public static function invalidInputs(): iterable
    {
        $id = PageIdMother::DEFAULT_VALUE;

        yield 'id is not a UUID' => ['page-id', 'Posts', InvalidUuid::withValue('page-id')];
        yield 'empty title' => [$id, '   ', InvalidPageTitle::tooShort(PageTitle::MIN_LENGTH)];
        yield 'title one character too short' => [$id, 'Sí', InvalidPageTitle::tooShort(PageTitle::MIN_LENGTH)];
        yield 'title one character too long' => [$id, str_repeat('a', 101), InvalidPageTitle::tooLong(PageTitle::MAX_LENGTH)];
        yield 'title without sluggable characters' => [$id, '¡!?', InvalidSlug::malformed('')];
        yield 'slug far too short' => [$id, 'C++', InvalidSlug::tooShort(Slug::MIN_LENGTH)];
        yield 'slug one character too short' => [$id, 'Ab!', InvalidSlug::tooShort(Slug::MIN_LENGTH)];
        yield 'slug one character too long' => [$id, str_repeat('a', 81), InvalidSlug::tooLong(Slug::MAX_LENGTH)];
    }

    public function testItPersistsNothingWhenAnotherPageHasTheSlugDerivedFromTheTitle(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('findBySlug')
            ->with(self::equalTo(SlugMother::create('sobre-mi')))
            ->willReturn(PageMother::create(id: PageIdMother::random()->value(), title: 'Sobre mí', slug: 'sobre-mi'));
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(PageSlugAlreadyTaken::bySlug(SlugMother::create('sobre-mi')));

        (new PageCreator(PageIdMother::DEFAULT_VALUE, 'Sobre mí'))($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }

    /**
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('refusals')]
    public function testItTellsWhyTheInputIsRefused(string $title, string $key, array $parameters): void
    {
        self::assertTells($key, $parameters, static fn () => (new PageCreator(PageIdMother::DEFAULT_VALUE, $title))(
            self::createStub(PageRepository::class),
            self::createStub(EventBus::class),
            new RecordingTransaction(),
        ));
    }

    /**
     * @return iterable<string, array{string, string, array<string, int|string>}>
     */
    public static function refusals(): iterable
    {
        yield 'title too short' => ['Sí', 'errors.page.title.too_short', ['%min%' => 3]];
        yield 'title too long' => [str_repeat('a', 101), 'errors.page.title.too_long', ['%max%' => 100]];
        yield 'title without sluggable characters' => ['¡!?', 'errors.slug.malformed', ['%slug%' => '']];
        yield 'slug too short' => ['C++', 'errors.slug.too_short', ['%min%' => 3]];
        yield 'slug too long' => [str_repeat('a', 81), 'errors.slug.too_long', ['%max%' => 80]];
    }

    public function testItTellsWhichSlugAnotherPageHas(): void
    {
        $repository = self::createStub(PageRepository::class);
        $repository->method('findBySlug')
            ->willReturn(PageMother::create(id: PageIdMother::random()->value(), title: 'Sobre mí', slug: 'sobre-mi'));

        self::assertTells('errors.page.slug.taken', ['%slug%' => 'sobre-mi'], static fn () => (new PageCreator(PageIdMother::DEFAULT_VALUE, 'Sobre mí'))(
            $repository,
            self::createStub(EventBus::class),
            new RecordingTransaction(),
        ));
    }
}
