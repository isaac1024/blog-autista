<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageAtAddressFinder;
use Cms\Page\Application\PageResponse;
use Cms\Page\Domain\PageRepository;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Domain\SlugMother;
use PHPUnit\Framework\TestCase;

final class PageAtAddressFinderTest extends TestCase
{
    public function testItFindsThePageTheSiteServesAtTheAddress(): void
    {
        $page = PageMother::published();
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())->method('findOnSiteBySlug')->with(self::equalTo(SlugMother::create()))->willReturn($page);

        self::assertEquals(PageResponse::fromPage($page), new PageAtAddressFinder(SlugMother::DEFAULT_VALUE)->onSite($repository));
    }

    public function testItFindsThePageOffTheSiteWhoseLastAddressItWas(): void
    {
        $page = PageMother::unpublished();
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())->method('findOffSiteByLastSiteSlug')->with(self::equalTo(SlugMother::create()))->willReturn($page);

        self::assertEquals(PageResponse::fromPage($page), new PageAtAddressFinder(SlugMother::DEFAULT_VALUE)->lastOffSite($repository));
    }

    public function testItFindsNoneWhereNoPageIs(): void
    {
        $repository = self::createStub(PageRepository::class);

        self::assertNull(new PageAtAddressFinder(SlugMother::DEFAULT_VALUE)->onSite($repository));
        self::assertNull(new PageAtAddressFinder(SlugMother::DEFAULT_VALUE)->lastOffSite($repository));
    }

    /**
     * What can't be the address of a page finds none, without asking.
     */
    public function testItFindsNoneAtWhatIsNoAddress(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::never())->method('findOnSiteBySlug');
        $repository->expects(self::never())->method('findOffSiteByLastSiteSlug');

        self::assertNull(new PageAtAddressFinder('Sobre Mí')->onSite($repository));
        self::assertNull(new PageAtAddressFinder('Sobre Mí')->lastOffSite($repository));
    }
}
