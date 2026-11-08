<?php

declare(strict_types=1);

namespace Web\Tests\Page\Application;

use PHPUnit\Framework\TestCase;
use Shared\Domain\RichText\RichText;
use Web\Page\Application\HomePageFinder;
use Web\Page\Application\PageNotFound;
use Web\Page\Application\PageResponse;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Page\Domain\Robots;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Page\Domain\PageIdMother;

final class HomePageFinderTest extends TestCase
{
    public function testItHandsOverTheHomePage(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('findHome')
            ->willReturn(Page::publish(CmsPagePublishedMother::create(title: 'Inicio', slug: null, type: PageType::Home, robots: Robots::IndexFollow)));

        self::assertEquals(
            new PageResponse(
                PageIdMother::DEFAULT_VALUE,
                'Inicio',
                null,
                RichText::fromArray(CmsPagePublishedMother::CONTENT),
                'Quién escribe este blog sobre autismo y por qué.',
                'index,follow',
            ),
            (new HomePageFinder())($repository),
        );
    }

    public function testItThrowsWhenTheHomePageIsNotOnTheSite(): void
    {
        $repository = self::createStub(PageRepository::class);
        $repository->method('findHome')->willReturn(null);

        $this->expectExceptionObject(PageNotFound::home());

        (new HomePageFinder())($repository);
    }
}
