<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Application;

use Cms\Image\Application\ImageChangesDiscarder;
use Cms\Image\Application\ImageNotFound;
use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RecordingTransaction;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ImageChangesDiscarderTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    private const string BEFORE = '2026-10-01 09:00:00';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItBringsTheEditedVersionBackToTheOneOnTheSite(): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn(ImageMother::published(
            alt: 'Otro texto',
            caption: Text::stored(Text::paragraph('Otro pie.')),
            publishedAlt: ImageMother::ALT,
            publishedCaption: ImageMother::CAPTION,
            createdAt: self::BEFORE,
        ));
        $repository->expects(self::once())->method('save')->with(self::equalTo(
            ImageMother::published(createdAt: self::BEFORE, updatedAt: ClockMother::NOW),
        ));

        (new ImageChangesDiscarder(ImageIdMother::DEFAULT_VALUE))($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }

    public function testItTellsTheSiteNothing(): void
    {
        $repository = self::createStub(ImageRepository::class);
        $repository->method('find')->willReturn(ImageMother::published(alt: 'Otro texto', publishedAlt: ImageMother::ALT));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with();

        (new ImageChangesDiscarder(ImageIdMother::DEFAULT_VALUE))($repository, $eventBus, new RecordingTransaction());
    }

    #[DataProvider('imagesWithoutChanges')]
    public function testItDiscardsNothingWithoutUnpublishedChanges(Image $image): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn($image);
        $repository->expects(self::never())->method('save');

        self::assertTells('errors.image.publication.no_changes_to_discard', [], static fn () => (new ImageChangesDiscarder(ImageIdMother::DEFAULT_VALUE))(
            $repository,
            self::createStub(EventBus::class),
            new RecordingTransaction(),
        ));
    }

    /**
     * @return iterable<string, array{Image}>
     */
    public static function imagesWithoutChanges(): iterable
    {
        yield 'a draft' => [ImageMother::create(caption: ImageMother::CAPTION)];
        yield 'a published image as it is on the site' => [ImageMother::published()];
        yield 'an unpublished image' => [ImageMother::unpublished()];
        yield 'an unpublished image, even edited since' => [ImageMother::unpublished(alt: 'Otro texto', publishedAlt: ImageMother::ALT)];
    }

    public function testItFailsWhenTheImageDoesNotExist(): void
    {
        $repository = self::createStub(ImageRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ImageNotFound::withId(ImageIdMother::DEFAULT_VALUE));

        (new ImageChangesDiscarder(ImageIdMother::DEFAULT_VALUE))($repository, self::createStub(EventBus::class), new RecordingTransaction());
    }
}
