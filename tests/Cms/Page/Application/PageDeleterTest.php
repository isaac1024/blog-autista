<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageDeleter;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Domain\PageRepository;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
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
