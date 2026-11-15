<?php

declare(strict_types=1);

namespace Web\Tests\External\Application;

use PHPUnit\Framework\TestCase;
use Shared\Domain\External\ImageShape;
use Web\External\Application\ExternalResourceRemover;
use Web\External\Domain\ExternalResource;
use Web\External\Domain\ExternalResourceRepository;
use Web\External\Domain\ImageCopies;
use Web\Tests\External\Domain\CmsExternalResourcePublishedMother;
use Web\Tests\External\Domain\CmsExternalResourceUnpublishedMother;
use Web\Tests\External\Domain\ExternalResourceIdMother;

final class ExternalResourceRemoverTest extends TestCase
{
    public function testItTakesTheResourceOffTheSiteWithTheCopiesOfItsImage(): void
    {
        $resource = ExternalResource::publish(CmsExternalResourcePublishedMother::create());
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(ExternalResourceIdMother::create()))
            ->willReturn($resource);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($resource));
        $copies = $this->createMock(ImageCopies::class);
        $copies->expects(self::once())->method('remove')->with(CmsExternalResourcePublishedMother::IMAGE, ImageShape::Landscape);

        (new ExternalResourceRemover(CmsExternalResourceUnpublishedMother::create()))($repository, $copies);
    }

    public function testItRemovesNoCopyOfAResourceWithoutAnImage(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(ExternalResource::publish(CmsExternalResourcePublishedMother::create(image: null, imageShape: null)));
        $repository->expects(self::once())->method('delete');
        $copies = $this->createMock(ImageCopies::class);
        $copies->expects(self::never())->method('remove');

        (new ExternalResourceRemover(CmsExternalResourceUnpublishedMother::create()))($repository, $copies);
    }

    public function testItDoesNothingWhenTheResourceIsNotOnTheSite(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('delete');
        $copies = $this->createMock(ImageCopies::class);
        $copies->expects(self::never())->method('remove');

        (new ExternalResourceRemover(CmsExternalResourceUnpublishedMother::create()))($repository, $copies);
    }
}
