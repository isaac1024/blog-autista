<?php

declare(strict_types=1);

namespace Web\Tests\Page\Application;

use PHPUnit\Framework\TestCase;
use Web\Page\Application\PageRemover;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Page\Domain\CmsPageUnpublishedMother;
use Web\Tests\Page\Domain\PageIdMother;

final class PageRemoverTest extends TestCase
{
    public function testItTakesThePageOffTheSite(): void
    {
        $page = Page::publish(CmsPagePublishedMother::create(occurredOn: '2026-11-07 09:00:00'));
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(PageIdMother::create()))
            ->willReturn($page);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($page));

        (new PageRemover(CmsPageUnpublishedMother::create()))($repository);
    }

    public function testItTakesOffAPagePublishedInTheSameMoment(): void
    {
        $page = Page::publish(CmsPagePublishedMother::create());
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($page));

        (new PageRemover(CmsPageUnpublishedMother::create()))($repository);
    }

    public function testItDoesNothingWhenThePageIsNotOnTheSite(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('delete');

        (new PageRemover(CmsPageUnpublishedMother::create()))($repository);
    }
}
