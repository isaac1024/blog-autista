<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Application;

use Cms\Image\Application\ImageResponse;
use Cms\Image\Application\ImagesFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use PHPUnit\Framework\TestCase;

final class ImagesFinderTest extends TestCase
{
    private const string CIRCLE = '01a1b801-ce58-723d-8234-b76d9105d001';
    private const string SQUARE = '01a1b801-ce58-723d-8234-b76d9105d002';
    private const string MISSING = '01a1b801-ce58-723d-8234-b76d9105d003';

    /**
     * One query for every id, each once: what isn't an id never reaches it.
     */
    public function testItAsksTheRepositoryForEveryValidIdOnce(): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::once())
            ->method('findByIds')
            ->with(self::equalTo([ImageIdMother::create(self::SQUARE), ImageIdMother::create(self::CIRCLE)]))
            ->willReturn([]);

        (new ImagesFinder([self::SQUARE, 'imagen', self::CIRCLE, self::SQUARE]))($repository);
    }

    /**
     * In the order asked, whatever order the repository answers in; an id of
     * no image is left out.
     */
    public function testItGivesTheImagesFoundInTheOrderAsked(): void
    {
        $circle = ImageMother::create(id: self::CIRCLE, alt: 'Círculo naranja sobre fondo azul');
        $square = ImageMother::create(id: self::SQUARE, alt: 'Cuadrado blanco sobre fondo verde');
        $repository = self::createStub(ImageRepository::class);
        $repository->method('findByIds')->willReturn([$circle, $square]);

        self::assertEquals(
            [ImageResponse::fromImage($square), ImageResponse::fromImage($circle)],
            (new ImagesFinder([self::SQUARE, self::MISSING, self::CIRCLE]))($repository),
        );
    }

    public function testItFindsNothingWithoutIds(): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::once())->method('findByIds')->with([])->willReturn([]);

        self::assertSame([], (new ImagesFinder([]))($repository));
    }
}
