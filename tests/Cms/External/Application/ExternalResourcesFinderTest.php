<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\ExternalResourceResponse;
use Cms\External\Application\ExternalResourcesFinder;
use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\TestCase;

final class ExternalResourcesFinderTest extends TestCase
{
    private const string OTHER = '01a1e2a0-0000-7000-8000-000000000002';

    /**
     * What is not the id of a resource finds none, and stops none of the rest.
     */
    public function testItFindsTheResourcesWithTheGivenIdsAsTheCmsShowsThem(): void
    {
        $video = ExternalResourceMother::video();
        $article = ExternalResourceMother::article(id: self::OTHER);
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::once())
            ->method('findByIds')
            ->with(self::equalTo([new ExternalResourceId(ExternalResourceMother::ID), new ExternalResourceId(self::OTHER)]))
            ->willReturn([$video, $article]);

        self::assertEquals(
            [ExternalResourceResponse::fromResource($video), ExternalResourceResponse::fromResource($article)],
            new ExternalResourcesFinder([ExternalResourceMother::ID, 'no-es-un-id', self::OTHER])($repository),
        );
    }
}
