<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Application;

use Cms\Image\Application\ImageFinder;
use Cms\Image\Application\ImageNotFound;
use Cms\Image\Application\ImageResponse;
use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\RichText\RichTextMother as Text;

final class ImageFinderTest extends TestCase
{
    public function testItReturnsTheImageWithTheRequestedId(): void
    {
        $id = ImageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(ImageIdMother::create($id)))
            ->willReturn(ImageMother::create(id: $id, alt: 'Círculo naranja sobre fondo azul'));

        self::assertEquals(
            new ImageResponse($id, 'Círculo naranja sobre fondo azul', null, '2026/11/'.$id.'.webp', ClockMother::now(), ClockMother::now()),
            (new ImageFinder($id))($repository),
        );
    }

    public function testItHandsOverTheCaptionAndWhenItLastChanged(): void
    {
        $id = ImageIdMother::DEFAULT_VALUE;
        $caption = Text::stored(Text::paragraph('Ilustración de archivo.'));
        $repository = self::createStub(ImageRepository::class);
        $repository->method('find')->willReturn(ImageMother::create(id: $id, caption: $caption, updatedAt: '2026-12-01 08:00:00'));

        self::assertEquals(
            new ImageResponse($id, ImageMother::ALT, $caption, '2026/11/'.$id.'.webp', ClockMother::now(), ClockMother::now('2026-12-01 08:00:00')),
            (new ImageFinder($id))($repository),
        );
    }

    public function testItFailsWhenTheImageDoesNotExist(): void
    {
        $id = ImageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(ImageRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ImageNotFound::withId($id));

        (new ImageFinder($id))($repository);
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::never())->method('find');

        $this->expectExceptionObject(InvalidUuid::withValue('image-id'));

        (new ImageFinder('image-id'))($repository);
    }
}
