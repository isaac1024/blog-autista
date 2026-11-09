<?php

declare(strict_types=1);

namespace Web\Tests\Image\Application;

use PHPUnit\Framework\TestCase;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Web\Image\Application\ImageSaver;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageRepository;
use Web\Image\Domain\ImageVariants;
use Web\Tests\Image\Domain\CmsImagePublishedMother;
use Web\Tests\Image\Domain\ImageIdMother;

final class ImageSaverTest extends TestCase
{
    /**
     * Its copies go first, so the site never has an image it can't serve.
     */
    public function testItMakesTheCopiesAndThenPutsANewImageOnTheSite(): void
    {
        $steps = [];
        $event = CmsImagePublishedMother::create();
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::once())->method('find')->with(self::equalTo(ImageIdMother::create()))->willReturn(null);
        $repository->expects(self::once())->method('save')->with(self::equalTo(Image::publish($event)))
            ->willReturnCallback(static function () use (&$steps): void {
                $steps[] = 'image';
            });
        $variants = $this->createMock(ImageVariants::class);
        $variants->expects(self::once())->method('publish')->with(CmsImagePublishedMother::PATH)
            ->willReturnCallback(static function () use (&$steps): void {
                $steps[] = 'copies';
            });
        $variants->expects(self::never())->method('remove');

        (new ImageSaver($event))($repository, $variants);

        self::assertSame(['copies', 'image'], $steps);
    }

    public function testItReplacesTheTextsOfAnImageAlreadyOnTheSite(): void
    {
        $caption = Text::stored(Text::paragraph('Otro pie.'));
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn(Image::publish(CmsImagePublishedMother::create()));
        $repository->expects(self::once())->method('save')->with(self::equalTo(
            Image::publish(CmsImagePublishedMother::create(alt: 'Otro texto', caption: $caption, occurredOn: '2026-11-07 11:00:00')),
        ));

        (new ImageSaver(CmsImagePublishedMother::create(alt: 'Otro texto', caption: $caption, occurredOn: '2026-11-07 11:00:00')))(
            $repository,
            self::createStub(ImageVariants::class),
        );
    }

    /**
     * Only a later event is kept over a new one: two from the same moment
     * keep the one taken last.
     */
    public function testItTakesAVersionPublishedAtTheSameMoment(): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn(Image::publish(CmsImagePublishedMother::create()));
        $repository->expects(self::once())->method('save')->with(self::equalTo(
            Image::publish(CmsImagePublishedMother::create(alt: 'Otro texto')),
        ));

        (new ImageSaver(CmsImagePublishedMother::create(alt: 'Otro texto')))($repository, self::createStub(ImageVariants::class));
    }

    public function testItRemovesTheCopiesWhenTheImageCannotBeSaved(): void
    {
        $failure = new \RuntimeException('The database is down.');
        $repository = self::createStub(ImageRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->method('save')->willThrowException($failure);
        $variants = $this->createMock(ImageVariants::class);
        $variants->expects(self::once())->method('publish')->with(CmsImagePublishedMother::PATH);
        $variants->expects(self::once())->method('remove')->with(CmsImagePublishedMother::PATH);

        $this->expectExceptionObject($failure);

        (new ImageSaver(CmsImagePublishedMother::create()))($repository, $variants);
    }

    public function testItSavesNothingWhenTheCopiesCannotBeMade(): void
    {
        $failure = new \RuntimeException('The disk is full.');
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('save');
        $variants = $this->createMock(ImageVariants::class);
        $variants->method('publish')->willThrowException($failure);
        $variants->expects(self::never())->method('remove');

        $this->expectExceptionObject($failure);

        (new ImageSaver(CmsImagePublishedMother::create()))($repository, $variants);
    }
}
