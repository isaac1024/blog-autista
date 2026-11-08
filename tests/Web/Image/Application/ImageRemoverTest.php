<?php

declare(strict_types=1);

namespace Web\Tests\Image\Application;

use PHPUnit\Framework\TestCase;
use Web\Image\Application\ImageRemover;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageRepository;
use Web\Image\Domain\ImageVariants;
use Web\Tests\Image\Domain\CmsImagePublishedMother;
use Web\Tests\Image\Domain\CmsImageUnpublishedMother;
use Web\Tests\Image\Domain\ImageIdMother;

final class ImageRemoverTest extends TestCase
{
    /**
     * Its copies go first, so the site stops serving them even if removing
     * the image fails.
     */
    public function testItRemovesTheCopiesAndThenTheImage(): void
    {
        $steps = [];
        $image = Image::publish(CmsImagePublishedMother::create());
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::once())->method('find')->with(self::equalTo(ImageIdMother::create()))->willReturn($image);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($image))
            ->willReturnCallback(static function () use (&$steps): void {
                $steps[] = 'image';
            });
        $variants = $this->createMock(ImageVariants::class);
        $variants->expects(self::once())->method('remove')->with(CmsImagePublishedMother::PATH)
            ->willReturnCallback(static function () use (&$steps): void {
                $steps[] = 'copies';
            });

        (new ImageRemover(CmsImageUnpublishedMother::create(occurredOn: '2026-11-07 11:00:00')))($repository, $variants);

        self::assertSame(['copies', 'image'], $steps);
    }

    public function testItKeepsTheImageWhenItWasPublishedAgainAfterwards(): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn(Image::publish(CmsImagePublishedMother::create(occurredOn: '2026-11-07 11:00:00')));
        $repository->expects(self::never())->method('delete');
        $variants = $this->createMock(ImageVariants::class);
        $variants->expects(self::never())->method('remove');

        (new ImageRemover(CmsImageUnpublishedMother::create(occurredOn: '2026-11-07 09:00:00')))($repository, $variants);
    }

    public function testItDoesNothingWhenTheImageIsAlreadyGone(): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('delete');
        $variants = $this->createMock(ImageVariants::class);
        $variants->expects(self::never())->method('remove');

        (new ImageRemover(CmsImageUnpublishedMother::create()))($repository, $variants);
    }
}
