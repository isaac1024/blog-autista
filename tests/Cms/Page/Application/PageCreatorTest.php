<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageCreator;
use Cms\Page\Domain\InvalidSlug;
use Cms\Page\Domain\InvalidTitle;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\Slug;
use Cms\Page\Domain\SlugAlreadyTaken;
use Cms\Page\Domain\Title;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Page\Domain\SlugMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PageCreatorTest extends TestCase
{
    use ClockSensitiveTrait;

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

        (new PageCreator($id, $title))($repository, self::createStub(EventBus::class));
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

        (new PageCreator($id, $title))($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{string, string, DomainError}>
     */
    public static function invalidInputs(): iterable
    {
        $id = PageIdMother::DEFAULT_VALUE;

        yield 'id is not a UUID' => ['page-id', 'Posts', InvalidUuid::withValue('page-id')];
        yield 'empty title' => [$id, '   ', InvalidTitle::tooShort(Title::MIN_LENGTH)];
        yield 'title one character too short' => [$id, 'Sí', InvalidTitle::tooShort(Title::MIN_LENGTH)];
        yield 'title one character too long' => [$id, str_repeat('a', 101), InvalidTitle::tooLong(Title::MAX_LENGTH)];
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

        $this->expectExceptionObject(SlugAlreadyTaken::bySlug(SlugMother::create('sobre-mi')));

        (new PageCreator(PageIdMother::DEFAULT_VALUE, 'Sobre mí'))($repository, self::createStub(EventBus::class));
    }
}
