<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Application;

use Cms\Image\Application\ImageNotFound;
use Cms\Image\Application\ImagePublisher;
use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImagePublished;
use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ImagePublisherTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    private const string BEFORE = '2026-10-01 09:00:00';

    #[\Override]
    protected function setUp(): void
    {
        // The domain dates what happens, so it is dated as the images compared with.
        self::mockTime(ClockMother::now());
    }

    #[DataProvider('publishableImages')]
    public function testItPutsTheSavedVersionOnTheSite(Image $image, Image $expected): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn($image);
        $repository->expects(self::once())->method('save')->with(self::equalTo($expected));

        (new ImagePublisher(ImageIdMother::DEFAULT_VALUE))($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{Image, Image}>
     */
    public static function publishableImages(): iterable
    {
        yield 'a draft with a caption' => [
            ImageMother::create(caption: ImageMother::CAPTION, createdAt: self::BEFORE),
            ImageMother::published(createdAt: self::BEFORE),
        ];
        yield 'a published image with changes' => [
            ImageMother::published(alt: 'Otro texto', publishedAlt: ImageMother::ALT, createdAt: self::BEFORE),
            ImageMother::published(alt: 'Otro texto', createdAt: self::BEFORE),
        ];
        yield 'an unpublished image' => [
            ImageMother::unpublished(createdAt: self::BEFORE),
            ImageMother::published(createdAt: self::BEFORE),
        ];
    }

    public function testItTellsTheSiteWhatToServe(): void
    {
        $id = ImageIdMother::DEFAULT_VALUE;
        $caption = Text::stored(Text::paragraph('Otro pie.'));
        $repository = self::createStub(ImageRepository::class);
        $repository->method('find')->willReturn(ImageMother::published(id: $id, alt: 'Otro texto', caption: $caption, publishedAlt: ImageMother::ALT, publishedCaption: ImageMother::CAPTION));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof ImagePublished
                && $id === $event->aggregateId()
                && ClockMother::now()->format(\DATE_ATOM) === $event->occurredOn()->format(\DATE_ATOM)
                && ['alt' => 'Otro texto', 'caption' => $caption, 'path' => '2026/11/'.$id.'.webp'] === $event->toPrimitives(),
        ));

        (new ImagePublisher($id))($repository, $eventBus);
    }

    /**
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('unpublishableImages')]
    public function testItPublishesNothingWhenItCannot(Image $image, string $key, array $parameters): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn($image);
        $repository->expects(self::never())->method('save');
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::never())->method('publish');

        self::assertTells($key, $parameters, static fn () => (new ImagePublisher(ImageIdMother::DEFAULT_VALUE))($repository, $eventBus));
    }

    /**
     * @return iterable<string, array{Image, string, array<string, int|string>}>
     */
    public static function unpublishableImages(): iterable
    {
        yield 'a draft without a caption' => [ImageMother::create(), 'errors.image.publication.without_caption', []];
        yield 'a published image as it is on the site' => [ImageMother::published(), 'errors.image.publication.nothing_to_publish', []];
    }

    public function testItFailsWhenTheImageDoesNotExist(): void
    {
        $repository = self::createStub(ImageRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ImageNotFound::withId(ImageIdMother::DEFAULT_VALUE));

        (new ImagePublisher(ImageIdMother::DEFAULT_VALUE))($repository, self::createStub(EventBus::class));
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::never())->method('find');

        $this->expectExceptionObject(InvalidUuid::withValue('image-id'));

        (new ImagePublisher('image-id'))($repository, self::createStub(EventBus::class));
    }
}
