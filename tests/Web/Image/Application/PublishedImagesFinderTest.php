<?php

declare(strict_types=1);

namespace Web\Tests\Image\Application;

use PHPUnit\Framework\TestCase;
use Shared\Domain\RichText\RichText;
use Web\Image\Application\ImageResponse;
use Web\Image\Application\PublishedImagesFinder;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageRepository;
use Web\Tests\Image\Domain\CmsImagePublishedMother;
use Web\Tests\Image\Domain\ImageIdMother;

final class PublishedImagesFinderTest extends TestCase
{
    private const string OTHER = '01a1b801-ce58-723d-8234-b76d9105d002';

    /**
     * It asks only for ids of images, and gives what the site has keyed by
     * id: an image it doesn't have is left out.
     */
    public function testItGivesTheImagesOnTheSiteKeyedById(): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::once())
            ->method('findByIds')
            ->with(self::equalTo([ImageIdMother::create(), ImageIdMother::create(self::OTHER)]))
            ->willReturn([
                Image::publish(CmsImagePublishedMother::create()),
                Image::publish(CmsImagePublishedMother::create(id: self::OTHER, alt: 'Otro texto', path: '2026/11/'.self::OTHER.'.webp')),
            ]);

        self::assertEquals(
            [
                ImageIdMother::DEFAULT_VALUE => new ImageResponse(
                    CmsImagePublishedMother::ALT,
                    RichText::fromArray(CmsImagePublishedMother::CAPTION),
                    CmsImagePublishedMother::PATH,
                ),
                self::OTHER => new ImageResponse('Otro texto', RichText::fromArray(CmsImagePublishedMother::CAPTION), '2026/11/'.self::OTHER.'.webp'),
            ],
            (new PublishedImagesFinder([ImageIdMother::DEFAULT_VALUE, 'foto.webp', self::OTHER]))($repository),
        );
    }

    public function testItGivesNoImageForAPageThatEmbedsNone(): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::once())->method('findByIds')->with([])->willReturn([]);

        self::assertSame([], (new PublishedImagesFinder([]))($repository));
    }
}
