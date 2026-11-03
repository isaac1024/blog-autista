<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PageUpdater;
use Cms\Page\Domain\InvalidSlug;
use Cms\Page\Domain\InvalidTitle;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\Slug;
use Cms\Page\Domain\Title;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\DomainError;
use Shared\Domain\InvalidUuid;

final class PageUpdaterTest extends TestCase
{
    #[DataProvider('validEdits')]
    public function testItSavesThePageWithTheEditedTitleAndSlug(string $title, string $slug, Page $expected): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(PageIdMother::create($id)))
            ->willReturn(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo($expected));

        (new PageUpdater($id, $title, $slug))($repository);
    }

    /**
     * @return iterable<string, array{string, string, Page}>
     */
    public static function validEdits(): iterable
    {
        $id = PageIdMother::DEFAULT_VALUE;

        yield 'new title and slug' => ['Quién soy', 'quien-soy', PageMother::create(id: $id, title: 'Quién soy', slug: 'quien-soy')];
        yield 'the slug is kept, not derived from the new title' => ['Quién soy', 'sobre-mi', PageMother::create(id: $id, title: 'Quién soy', slug: 'sobre-mi')];
        yield 'surrounding whitespace of the title is trimmed' => ['  Quién soy  ', 'quien-soy', PageMother::create(id: $id, title: 'Quién soy', slug: 'quien-soy')];
        yield 'nothing changed' => ['Sobre mí', 'sobre-mi', PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi')];
        yield 'shortest title and slug' => ['FAQ', 'faq', PageMother::create(id: $id, title: 'FAQ', slug: 'faq')];
        yield 'longest title and slug' => [
            str_repeat('a', 100),
            str_repeat('a', 80),
            PageMother::create(id: $id, title: str_repeat('a', 100), slug: str_repeat('a', 80)),
        ];
        yield 'longest title with multibyte characters' => [
            str_repeat('á', 100),
            'acentos',
            PageMother::create(id: $id, title: str_repeat('á', 100), slug: 'acentos'),
        ];
        yield 'slug with digits and single hyphens' => ['Guía 2026', 'guia-2026-v2', PageMother::create(id: $id, title: 'Guía 2026', slug: 'guia-2026-v2')];
    }

    #[DataProvider('invalidEdits')]
    public function testItSavesNothingWhenTheEditIsInvalid(string $title, string $slug, DomainError $error): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject($error);

        (new PageUpdater($id, $title, $slug))($repository);
    }

    /**
     * @return iterable<string, array{string, string, DomainError}>
     */
    public static function invalidEdits(): iterable
    {
        yield 'empty title' => ['   ', 'quien-soy', InvalidTitle::tooShort(Title::MIN_LENGTH)];
        yield 'title one character too short' => ['Sí', 'quien-soy', InvalidTitle::tooShort(Title::MIN_LENGTH)];
        yield 'title one character too long' => [str_repeat('a', 101), 'quien-soy', InvalidTitle::tooLong(Title::MAX_LENGTH)];
        yield 'empty slug' => ['Quién soy', '', InvalidSlug::malformed('')];
        yield 'slug with uppercase letters' => ['Quién soy', 'Quien-Soy', InvalidSlug::malformed('Quien-Soy')];
        yield 'slug with accents' => ['Quién soy', 'quién-soy', InvalidSlug::malformed('quién-soy')];
        yield 'slug with spaces' => ['Quién soy', 'quien soy', InvalidSlug::malformed('quien soy')];
        yield 'slug with a double hyphen' => ['Quién soy', 'quien--soy', InvalidSlug::malformed('quien--soy')];
        yield 'slug starting with a hyphen' => ['Quién soy', '-quien-soy', InvalidSlug::malformed('-quien-soy')];
        yield 'slug ending with a hyphen' => ['Quién soy', 'quien-soy-', InvalidSlug::malformed('quien-soy-')];
        yield 'slug one character too short' => ['Quién soy', 'ab', InvalidSlug::tooShort(Slug::MIN_LENGTH)];
        yield 'slug one character too long' => ['Quién soy', str_repeat('a', 81), InvalidSlug::tooLong(Slug::MAX_LENGTH)];
    }

    public function testItThrowsWhenThePageDoesNotExist(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(PageNotFound::withId($id));

        (new PageUpdater($id, 'Quién soy', 'quien-soy'))($repository);
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::never())->method('find');
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidUuid::withValue('page-id'));

        (new PageUpdater('page-id', 'Quién soy', 'quien-soy'))($repository);
    }
}
