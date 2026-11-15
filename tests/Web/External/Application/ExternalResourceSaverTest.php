<?php

declare(strict_types=1);

namespace Web\Tests\External\Application;

use PHPUnit\Framework\TestCase;
use Shared\Domain\External\ImageShape;
use Web\External\Application\ExternalResourceSaver;
use Web\External\Domain\ExternalResource;
use Web\External\Domain\ExternalResourceRepository;
use Web\External\Domain\ImageCopies;
use Web\Tests\External\Domain\CmsExternalResourcePublishedMother;
use Web\Tests\External\Domain\ExternalResourceIdMother;

final class ExternalResourceSaverTest extends TestCase
{
    private const string OTHER_IMAGE = '01a1e2a0-0000-7000-8000-0000000000a2.webp';

    public function testItPutsANewResourceOnTheSiteWithTheCopiesOfItsImage(): void
    {
        $event = CmsExternalResourcePublishedMother::create();
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(ExternalResourceIdMother::create()))
            ->willReturn(null);
        $repository->expects(self::once())->method('save')->with(self::equalTo(ExternalResource::publish($event)));
        $copies = $this->createMock(ImageCopies::class);
        $copies->expects(self::once())->method('publish')->with(CmsExternalResourcePublishedMother::IMAGE, ImageShape::Landscape);
        $copies->expects(self::never())->method('remove');

        (new ExternalResourceSaver($event))($repository, $copies);
    }

    public function testItCopiesNothingForAResourceWithoutAnImage(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::once())->method('save');
        $copies = $this->createMock(ImageCopies::class);
        $copies->expects(self::never())->method('publish');

        (new ExternalResourceSaver(CmsExternalResourcePublishedMother::create(image: null, imageShape: null)))($repository, $copies);
    }

    public function testItReplacesTheResourceAndRemovesTheCopiesOfTheImageItHadOnceSaved(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResource::publish(CmsExternalResourcePublishedMother::create()));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (ExternalResource $resource): bool => ['Otro título', self::OTHER_IMAGE] === [$resource->title(), $resource->image()]));
        $copies = $this->createMock(ImageCopies::class);
        $copies->expects(self::once())->method('publish')->with(self::OTHER_IMAGE, ImageShape::Landscape);
        $copies->expects(self::once())->method('remove')->with(CmsExternalResourcePublishedMother::IMAGE, ImageShape::Landscape);

        (new ExternalResourceSaver(CmsExternalResourcePublishedMother::create(title: 'Otro título', image: self::OTHER_IMAGE)))($repository, $copies);
    }

    public function testItKeepsTheCopiesOfAnImageThatDoesNotChange(): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResource::publish(CmsExternalResourcePublishedMother::create()));
        $copies = $this->createMock(ImageCopies::class);
        $copies->expects(self::never())->method('remove');

        (new ExternalResourceSaver(CmsExternalResourcePublishedMother::create(title: 'Otro título')))($repository, $copies);
    }
}
