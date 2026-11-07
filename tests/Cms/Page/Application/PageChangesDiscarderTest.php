<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageChangesDiscarder;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Domain\InvalidPublication;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PageChangesDiscarderTest extends TestCase
{
    use ClockSensitiveTrait;

    private const string BEFORE = '2026-10-01 09:00:00';

    #[\Override]
    protected function setUp(): void
    {
        // The domain dates what happens, so it is dated as the pages compared with.
        self::mockTime(ClockMother::now());
    }

    public function testItBringsThePageBackToItsVersionOnTheSite(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::published(id: $id, title: 'Quién soy', publishedTitle: 'Sobre mí', updatedAt: self::BEFORE));
        $repository->expects(self::once())->method('save')->with(self::equalTo(PageMother::published(id: $id, title: 'Sobre mí')));

        (new PageChangesDiscarder($id))($repository);
    }

    #[DataProvider('pagesWithoutChanges')]
    public function testItDiscardsNothingWithoutUnpublishedChanges(Page $page): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidPublication::noChangesToDiscard());

        (new PageChangesDiscarder($page->id()->value()))($repository);
    }

    /**
     * @return iterable<string, array{Page}>
     */
    public static function pagesWithoutChanges(): iterable
    {
        yield 'a draft' => [PageMother::create()];
        yield 'a published page as it is on the site' => [PageMother::published()];
        yield 'an unpublished page edited since' => [PageMother::unpublished(title: 'Quién soy')];
    }

    public function testItThrowsWhenThePageDoesNotExist(): void
    {
        $id = PageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(PageNotFound::withId($id));

        (new PageChangesDiscarder($id))($repository);
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $this->expectExceptionObject(InvalidUuid::withValue('sobre-mi'));

        (new PageChangesDiscarder('sobre-mi'))(self::createStub(PageRepository::class));
    }
}
