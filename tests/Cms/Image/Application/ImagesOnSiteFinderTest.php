<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Application;

use Cms\Image\Application\ImageOnSiteResponse;
use Cms\Image\Application\ImagesOnSiteFinder;
use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageId;
use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\RichText\RichText;

final class ImagesOnSiteFinderTest extends TestCase
{
    private const string PUBLISHED = '01a1b801-ce58-723d-8234-b76d9105e101';
    private const string DRAFT = '01a1b801-ce58-723d-8234-b76d9105e102';
    private const string UNPUBLISHED = '01a1b801-ce58-723d-8234-b76d9105e103';
    private const string MISSING = '01a1b801-ce58-723d-8234-b76d9105e104';
    private const string OTHER_PUBLISHED = '01a1b801-ce58-723d-8234-b76d9105e105';

    private const array PUBLISHED_CAPTION = [
        'version' => 1,
        'blocks' => [['type' => 'paragraph', 'text' => [['text' => 'Pie publicado.']]]],
    ];

    /**
     * The site shows the alt and caption an image was published with, not
     * those edited since. An image off the site, an id of no image, or what
     * isn't an id at all, is left out, and the rest are still found.
     */
    public function testItGivesTheImagesOnTheSiteAsTheSiteServesThem(): void
    {
        $images = [
            self::PUBLISHED => ImageMother::published(id: self::PUBLISHED, alt: 'Alt editado', publishedAlt: 'Alt publicado', publishedCaption: self::PUBLISHED_CAPTION),
            self::DRAFT => ImageMother::create(id: self::DRAFT),
            self::UNPUBLISHED => ImageMother::unpublished(id: self::UNPUBLISHED),
            self::OTHER_PUBLISHED => ImageMother::published(id: self::OTHER_PUBLISHED),
        ];
        $repository = self::createStub(ImageRepository::class);
        $repository->method('findByIds')->willReturnCallback(static fn (array $ids): array => array_values(array_filter(array_map(static fn (mixed $id): ?Image => $id instanceof ImageId ? $images[$id->value()] ?? null : null, $ids))));

        self::assertEquals([
            self::PUBLISHED => new ImageOnSiteResponse(
                'Alt publicado',
                RichText::fromArray(self::PUBLISHED_CAPTION),
                ImageMother::path(self::PUBLISHED)->value(),
            ),
            self::OTHER_PUBLISHED => new ImageOnSiteResponse(
                ImageMother::ALT,
                RichText::fromArray(ImageMother::CAPTION),
                ImageMother::path(self::OTHER_PUBLISHED)->value(),
            ),
        ], new ImagesOnSiteFinder(['imagen', self::DRAFT, self::PUBLISHED, self::UNPUBLISHED, self::MISSING, self::OTHER_PUBLISHED])($repository));
    }

    /**
     * The images are read at once, what isn't an id left out.
     */
    public function testItReadsEveryImageAtOnce(): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::once())
            ->method('findByIds')
            ->with(self::equalTo([ImageIdMother::create(self::PUBLISHED), ImageIdMother::create(self::MISSING)]))
            ->willReturn([]);

        new ImagesOnSiteFinder([self::PUBLISHED, 'imagen', self::MISSING])($repository);
    }
}
