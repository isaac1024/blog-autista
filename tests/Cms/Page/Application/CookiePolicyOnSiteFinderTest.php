<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\CookiePolicyOnSiteFinder;
use Cms\Page\Application\PageOnSiteResponse;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\TestCase;

final class CookiePolicyOnSiteFinderTest extends TestCase
{
    /**
     * As the site serves it: with the title it was published with.
     */
    public function testItFindsTheCookiePolicyOnTheSite(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('findByType')
            ->with(PageType::CookiePolicy)
            ->willReturn(PageMother::published(title: 'Otra', slug: 'cookies', type: PageType::CookiePolicy, publishedTitle: 'Política de cookies'));

        self::assertEquals(new PageOnSiteResponse('Política de cookies', 'cookies', PageMother::META_DESCRIPTION), new CookiePolicyOnSiteFinder()($repository));
    }

    public function testItFindsNoneWhileTheCookiePolicyIsOffTheSite(): void
    {
        $repository = self::createStub(PageRepository::class);
        $repository->method('findByType')->willReturn(PageMother::create(slug: 'cookies', type: PageType::CookiePolicy));

        self::assertNull(new CookiePolicyOnSiteFinder()($repository));
    }

    public function testItFindsNoneWithoutACookiePolicy(): void
    {
        $repository = self::createStub(PageRepository::class);
        $repository->method('findByType')->willReturn(null);

        self::assertNull(new CookiePolicyOnSiteFinder()($repository));
    }
}
