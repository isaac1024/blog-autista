<?php

declare(strict_types=1);

namespace Web\Tests\Page\Application;

use PHPUnit\Framework\TestCase;
use Shared\Domain\RichText\RichText;
use Web\Page\Application\PageFinder;
use Web\Page\Application\PageNotFound;
use Web\Page\Application\PageResponse;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Shared\Domain\Robots;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Page\Domain\PageIdMother;

final class PageFinderTest extends TestCase
{
    public function testItHandsOverThePageAtTheSlug(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())
            ->method('findBySlug')
            ->with('sobre-mi')
            ->willReturn(Page::publish(CmsPagePublishedMother::create(robots: Robots::NoindexFollow)));

        self::assertEquals(
            new PageResponse(
                PageIdMother::DEFAULT_VALUE,
                'Sobre mí',
                'sobre-mi',
                RichText::fromArray(CmsPagePublishedMother::CONTENT),
                'Quién escribe este blog sobre autismo y por qué.',
                'noindex,follow',
                'standard',
            ),
            (new PageFinder('sobre-mi'))($repository),
        );
    }

    public function testItThrowsWhenNoPageIsAtTheSlug(): void
    {
        $repository = self::createStub(PageRepository::class);
        $repository->method('findBySlug')->willReturn(null);

        $this->expectExceptionObject(PageNotFound::withSlug('sobre-mi'));

        (new PageFinder('sobre-mi'))($repository);
    }
}
