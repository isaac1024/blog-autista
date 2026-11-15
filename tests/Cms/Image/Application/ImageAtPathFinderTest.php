<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Application;

use Cms\Image\Application\ImageAtPathFinder;
use Cms\Image\Application\ImageResponse;
use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageMother;
use PHPUnit\Framework\TestCase;

final class ImageAtPathFinderTest extends TestCase
{
    public function testItFindsTheImageKeptAtThePath(): void
    {
        $image = ImageMother::published();
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::once())->method('findByPath')->with(self::equalTo(ImageMother::path()))->willReturn($image);

        self::assertEquals(ImageResponse::fromImage($image), new ImageAtPathFinder(ImageMother::path()->value())($repository));
    }

    public function testItFindsNoneWhereNoImageIsKept(): void
    {
        $repository = self::createStub(ImageRepository::class);
        $repository->method('findByPath')->willReturn(null);

        self::assertNull(new ImageAtPathFinder(ImageMother::path()->value())($repository));
    }
}
