<?php

declare(strict_types=1);

namespace Web\Tests\External\Application;

use PHPUnit\Framework\TestCase;
use Web\External\Application\AvailabilityChanger;
use Web\External\Domain\ExternalResource;
use Web\External\Domain\ExternalResourceRepository;
use Web\External\Domain\ImageCopies;
use Web\Tests\External\Domain\CmsExternalResourceAvailabilityChangedMother;
use Web\Tests\External\Domain\CmsExternalResourcePublishedMother;
use Web\Tests\External\Domain\ExternalResourceIdMother;

final class AvailabilityChangerTest extends TestCase
{
    public function testItSavesTheResourceAsTheCheckFoundIt(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(ExternalResourceIdMother::create()))
            ->willReturn(ExternalResource::publish(CmsExternalResourcePublishedMother::create()));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(ExternalResource::publish(CmsExternalResourcePublishedMother::create(available: false))));

        (new AvailabilityChanger(CmsExternalResourceAvailabilityChangedMother::create(available: false)))($repository, self::createStub(ImageCopies::class));
    }

    public function testItDoesNothingWhenTheResourceIsNotOnTheSite(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('save');

        (new AvailabilityChanger(CmsExternalResourceAvailabilityChangedMother::create()))($repository, self::createStub(ImageCopies::class));
    }
}
