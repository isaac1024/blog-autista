<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\ExternalResourceOnSiteResponse;
use Cms\External\Application\ExternalResourcesOnSiteFinder;
use Cms\External\Domain\Availability;
use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceStatus;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\External\ResourceKind;

final class ExternalResourcesOnSiteFinderTest extends TestCase
{
    private const string DRAFT = '01a1e2a0-0000-7000-8000-000000000002';

    private const string GONE = '01a1e2a0-0000-7000-8000-000000000003';

    /**
     * A preview shows a resource as the site would: the version on the site,
     * and only one published and available. An id given twice is looked up
     * once; what is not an id, never.
     */
    public function testItFindsTheResourcesOnTheSiteWithTheVersionTheSiteHas(): void
    {
        $onSite = ExternalResourceMother::version(ResourceKind::Video, ExternalResourceMother::VIDEO_URL, ExternalResourceMother::VIDEO, 'En el sitio', [], ExternalResourceMother::IMAGE);
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::once())
            ->method('findByIds')
            ->with(self::equalTo([new ExternalResourceId(ExternalResourceMother::ID), new ExternalResourceId(self::DRAFT), new ExternalResourceId(self::GONE)]))
            ->willReturn([
                ExternalResourceMother::video(title: 'Editándose', status: ResourceStatus::Published, published: $onSite),
                ExternalResourceMother::video(id: self::DRAFT),
                ExternalResourceMother::video(id: self::GONE, status: ResourceStatus::Published, availability: Availability::Unavailable),
            ]);

        self::assertEquals(
            [ExternalResourceMother::ID => new ExternalResourceOnSiteResponse('video', 'youtube', ExternalResourceMother::VIDEO, ExternalResourceMother::VIDEO_URL, 'En el sitio', null, 'es', [], ExternalResourceMother::IMAGE, 'landscape')],
            new ExternalResourcesOnSiteFinder([ExternalResourceMother::ID, self::DRAFT, ExternalResourceMother::ID, 'no-es-un-id', self::GONE])($repository),
        );
    }

    public function testItFindsEveryResourceOnTheSiteWithTheShapeItsProviderGivesIt(): void
    {
        $tiktok = ExternalResourceMother::resource(self::GONE, ResourceKind::Video, 'tiktok', ExternalResourceMother::version(ResourceKind::Video, 'https://www.tiktok.com/@scout2015/video/6718335390845095173', 'scout2015/6718335390845095173', 'Un tiktok', [], ExternalResourceMother::IMAGE), ResourceStatus::Published, null, Availability::Available);
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('findByIds')->willReturn([ExternalResourceMother::video(status: ResourceStatus::Published), $tiktok]);

        $found = new ExternalResourcesOnSiteFinder([ExternalResourceMother::ID, self::GONE])($repository);

        self::assertSame([ExternalResourceMother::ID => 'landscape', self::GONE => 'portrait'], array_map(static fn (ExternalResourceOnSiteResponse $resource): ?string => $resource->imageShape, $found));
    }
}
