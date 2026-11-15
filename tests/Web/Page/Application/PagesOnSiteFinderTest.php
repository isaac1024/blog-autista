<?php

declare(strict_types=1);

namespace Web\Tests\Page\Application;

use PHPUnit\Framework\TestCase;
use Web\Page\Application\PageResponse;
use Web\Page\Application\PagesOnSiteFinder;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Page\Domain\PageIdMother;

final class PagesOnSiteFinderTest extends TestCase
{
    private const string OTHER = '01a1b801-ce58-723d-8234-b76d9105d002';

    /**
     * It asks only for ids of pages, and gives what the site has keyed by id:
     * a page it doesn't have is left out.
     */
    public function testItGivesThePagesOnTheSiteKeyedById(): void
    {
        $first = Page::publish(CmsPagePublishedMother::create());
        $other = Page::publish(CmsPagePublishedMother::create(id: self::OTHER, title: 'Contacto', slug: 'contacto'));
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('findByIds')
            ->with(self::equalTo([PageIdMother::create(), PageIdMother::create(self::OTHER)]))
            ->willReturn([$first, $other]);

        self::assertEquals(
            [
                PageIdMother::DEFAULT_VALUE => PageResponse::fromPage($first),
                self::OTHER => PageResponse::fromPage($other),
            ],
            (new PagesOnSiteFinder([PageIdMother::DEFAULT_VALUE, 'pagina', self::OTHER]))($repository),
        );
    }

    public function testItGivesNoPageForContentThatLinksNone(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())->method('findByIds')->with([])->willReturn([]);

        self::assertSame([], (new PagesOnSiteFinder([]))($repository));
    }
}
