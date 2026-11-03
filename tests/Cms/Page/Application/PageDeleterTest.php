<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageDeleter;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\UniquePageCannotBeDeleted;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\InvalidUuid;

final class PageDeleterTest extends TestCase
{
    public function testItDeletesThePageWithTheGivenId(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $page = PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi');
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(PageIdMother::create($id)))
            ->willReturn($page);
        $repository->expects(self::once())
            ->method('delete')
            ->with(self::identicalTo($page));

        (new PageDeleter($id))($repository);
    }

    #[DataProvider('uniquePages')]
    public function testItNeverDeletesAUniquePage(Page $page): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(UniquePageCannotBeDeleted::ofType($page->type()));

        (new PageDeleter($page->id()->value()))($repository);
    }

    /**
     * @return iterable<string, array{Page}>
     */
    public static function uniquePages(): iterable
    {
        yield 'home' => [PageMother::home()];

        foreach (PageMother::uniqueButHome() as $type => $page) {
            yield $type => [$page];
        }
    }

    public function testItThrowsWhenThePageDoesNotExist(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(PageNotFound::withId($id));

        (new PageDeleter($id))($repository);
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::never())->method('find');
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(InvalidUuid::withValue('page-id'));

        (new PageDeleter('page-id'))($repository);
    }
}
