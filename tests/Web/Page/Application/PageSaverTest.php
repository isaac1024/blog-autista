<?php

declare(strict_types=1);

namespace Web\Tests\Page\Application;

use PHPUnit\Framework\TestCase;
use Web\Page\Application\PageSaver;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Page\Domain\PageIdMother;

final class PageSaverTest extends TestCase
{
    private const string LATER = '2026-11-07 11:00:00';

    public function testItPutsANewPageOnTheSite(): void
    {
        $event = CmsPagePublishedMother::create();
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(PageIdMother::create()))
            ->willReturn(null);
        $repository->expects(self::once())->method('save')->with(self::equalTo(Page::publish($event)));

        (new PageSaver($event))($repository);
    }

    public function testItReplacesThePageOnTheSiteWithTheVersionPublishedLater(): void
    {
        $later = CmsPagePublishedMother::create(title: 'Contacto', slug: 'contacto', type: PageType::Contact, metaDescription: null, lastPublishedAt: self::LATER);
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(Page::publish(CmsPagePublishedMother::create()));
        $repository->expects(self::once())->method('save')->with(self::equalTo(Page::publish($later)));

        (new PageSaver($later))($repository);
    }

    public function testItTakesTheSameEventTwiceAsOnce(): void
    {
        $event = CmsPagePublishedMother::create();
        $repository = $this->createMock(PageRepository::class);
        $repository->method('find')->willReturn(Page::publish($event));
        $repository->expects(self::once())->method('save')->with(self::equalTo(Page::publish($event)));

        (new PageSaver($event))($repository);
    }
}
