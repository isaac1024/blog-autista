<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Application\ResourceImageFinder;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceStatus;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\External\ResourceKind;

final class ResourceImageFinderTest extends TestCase
{
    /**
     * The image of either version: the one edited, or the one the site has.
     */
    public function testItFindsAnImageOfTheResource(): void
    {
        $onSite = ExternalResourceMother::version(ResourceKind::Video, ExternalResourceMother::VIDEO_URL, ExternalResourceMother::VIDEO, 'Un vídeo', [], ExternalResourceMother::OTHER_IMAGE);
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video(status: ResourceStatus::Published, published: $onSite));

        self::assertSame(ExternalResourceMother::IMAGE, new ResourceImageFinder(ExternalResourceMother::ID, ExternalResourceMother::IMAGE)($repository));
        self::assertSame(ExternalResourceMother::OTHER_IMAGE, new ResourceImageFinder(ExternalResourceMother::ID, ExternalResourceMother::OTHER_IMAGE)($repository));
    }

    public function testItFindsNoImageTheResourceDoesNotHave(): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResourceMother::video());

        $this->expectExceptionObject(ExternalResourceNotFound::withId(ExternalResourceMother::ID));

        new ResourceImageFinder(ExternalResourceMother::ID, ExternalResourceMother::OTHER_IMAGE)($repository);
    }

    public function testItThrowsWhenTheResourceDoesNotExist(): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ExternalResourceNotFound::withId(ExternalResourceMother::ID));

        new ResourceImageFinder(ExternalResourceMother::ID, ExternalResourceMother::IMAGE)($repository);
    }
}
