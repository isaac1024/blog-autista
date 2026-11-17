<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageOnSiteResponse;
use Cms\Page\Application\SearchPageOnSiteFinder;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\TestCase;

final class SearchPageOnSiteFinderTest extends TestCase
{
    /**
     * As the site serves it: with the title it was published with.
     */
    public function testItFindsThePageThatSearchesOnTheSite(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('findByType')
            ->with(PageType::Search)
            ->willReturn(PageMother::published(title: 'Otra', slug: 'buscar', type: PageType::Search, publishedTitle: 'Buscar'));

        self::assertEquals(new PageOnSiteResponse('Buscar', 'buscar'), new SearchPageOnSiteFinder()($repository));
    }

    public function testItFindsNoneWhileThePageThatSearchesIsOffTheSite(): void
    {
        $repository = self::createStub(PageRepository::class);
        $repository->method('findByType')->willReturn(PageMother::create(slug: 'buscar', type: PageType::Search));

        self::assertNull(new SearchPageOnSiteFinder()($repository));
    }

    public function testItFindsNoneWithoutAPageThatSearches(): void
    {
        $repository = self::createStub(PageRepository::class);
        $repository->method('findByType')->willReturn(null);

        self::assertNull(new SearchPageOnSiteFinder()($repository));
    }
}
