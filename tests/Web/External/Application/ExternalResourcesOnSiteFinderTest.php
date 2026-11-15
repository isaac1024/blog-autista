<?php

declare(strict_types=1);

namespace Web\Tests\External\Application;

use PHPUnit\Framework\TestCase;
use Web\External\Application\ExternalResourceOnSiteResponse;
use Web\External\Application\ExternalResourcesOnSiteFinder;
use Web\External\Domain\ExternalResource;
use Web\External\Domain\ExternalResourceRepository;
use Web\Tests\External\Domain\CmsExternalResourcePublishedMother;
use Web\Tests\External\Domain\ExternalResourceIdMother;

final class ExternalResourcesOnSiteFinderTest extends TestCase
{
    public function testItHandsOverTheAvailableResourcesAmongThoseGivenByTheirIds(): void
    {
        $id = ExternalResourceIdMother::DEFAULT_VALUE;
        $other = ExternalResourceIdMother::random()->value();
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::once())
            ->method('availableAmong')
            ->with([$id, $other])
            ->willReturn([$id => ExternalResource::publish(CmsExternalResourcePublishedMother::create())]);

        self::assertEquals(
            [$id => new ExternalResourceOnSiteResponse('video', 'youtube', 'dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'Un vídeo', null, 'es', [], CmsExternalResourcePublishedMother::IMAGE, 'landscape')],
            (new ExternalResourcesOnSiteFinder([$id, $other]))($repository),
        );
    }
}
