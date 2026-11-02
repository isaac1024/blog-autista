<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageCreator;
use Cms\Page\Domain\InvalidSlug;
use Cms\Page\Domain\InvalidTitle;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\DomainError;
use Shared\Domain\InvalidUuid;

final class PageCreatorTest extends TestCase
{
    #[DataProvider('validInputs')]
    public function testItPersistsThePageBuiltFromValidInput(string $id, string $title, Page $expected): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo($expected));

        (new PageCreator($id, $title))($repository);
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
        yield 'shortest title' => [$id, 'FAQ', PageMother::create(id: $id, title: 'FAQ', slug: 'faq')];
    }

    /**
     * @param class-string<DomainError> $exception
     */
    #[DataProvider('invalidInputs')]
    public function testItPersistsNothingWhenTheInputIsInvalid(string $id, string $title, string $exception): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::never())->method('save');

        $this->expectException($exception);

        (new PageCreator($id, $title))($repository);
    }

    /**
     * @return iterable<string, array{string, string, class-string<DomainError>}>
     */
    public static function invalidInputs(): iterable
    {
        $id = PageIdMother::DEFAULT_VALUE;

        yield 'id is not a UUID' => ['page-id', 'Posts', InvalidUuid::class];
        yield 'empty title' => [$id, '   ', InvalidTitle::class];
        yield 'title too short' => [$id, 'Sí', InvalidTitle::class];
        yield 'title too long' => [$id, str_repeat('a', 101), InvalidTitle::class];
        yield 'title without sluggable characters' => [$id, '¡!?', InvalidSlug::class];
        yield 'title whose slug is too short' => [$id, 'C++', InvalidSlug::class];
    }
}
