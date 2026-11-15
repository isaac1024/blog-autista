<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Application;

use Cms\Page\Application\PageLastAddressFinder;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Domain\PageRepository;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Domain\SlugMother;
use PHPUnit\Framework\TestCase;

final class PageLastAddressFinderTest extends TestCase
{
    public function testItFindsTheLastAddressThePageHadOnTheSite(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects(self::once())->method('find')->with(self::equalTo(PageIdMother::create()))->willReturn(PageMother::unpublished());

        self::assertSame(SlugMother::DEFAULT_VALUE, new PageLastAddressFinder(PageIdMother::DEFAULT_VALUE)($repository));
    }

    public function testItFindsNoneForAPageNeverOnTheSite(): void
    {
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(PageMother::create());

        self::assertNull(new PageLastAddressFinder(PageIdMother::DEFAULT_VALUE)($repository));
    }

    public function testItThrowsWhenThePageDoesNotExist(): void
    {
        $repository = self::createStub(PageRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(PageNotFound::withId(PageIdMother::DEFAULT_VALUE));

        new PageLastAddressFinder(PageIdMother::DEFAULT_VALUE)($repository);
    }
}
