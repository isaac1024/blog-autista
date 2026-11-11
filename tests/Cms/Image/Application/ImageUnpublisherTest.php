<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Application;

use Cms\Image\Application\ImageNotFound;
use Cms\Image\Application\ImageUnpublisher;
use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageRedirects;
use Cms\Image\Domain\ImageRepository;
use Cms\Image\Domain\ImageUnpublished;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ImageUnpublisherTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItTakesThePublishedImageOffTheSite(): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn(ImageMother::published());
        // It keeps the version it had on the site.
        $repository->expects(self::once())->method('save')->with(self::equalTo(ImageMother::unpublished()));

        (new ImageUnpublisher(ImageIdMother::DEFAULT_VALUE))($repository, self::createStub(EventBus::class), self::createStub(ImageRedirects::class));
    }

    public function testItTellsTheSiteToTakeItOff(): void
    {
        $id = ImageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(ImageRepository::class);
        $repository->method('find')->willReturn(ImageMother::published(id: $id));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof ImageUnpublished
                && $id === $event->aggregateId()
                && ['revision' => 2] === $event->toPrimitives(),
        ));

        (new ImageUnpublisher($id))($repository, $eventBus, self::createStub(ImageRedirects::class));
    }

    #[DataProvider('imagesOffTheSite')]
    public function testItUnpublishesNothingThatIsNotPublished(Image $image): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn($image);
        $repository->expects(self::never())->method('save');
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::never())->method('publish');

        self::assertTells('errors.image.publication.not_published', [], static fn () => (new ImageUnpublisher(ImageIdMother::DEFAULT_VALUE))($repository, $eventBus, self::createStub(ImageRedirects::class)));
    }

    /**
     * @return iterable<string, array{Image}>
     */
    public static function imagesOffTheSite(): iterable
    {
        yield 'a draft' => [ImageMother::create()];
        yield 'an unpublished image' => [ImageMother::unpublished()];
    }

    public function testItFailsWhenTheImageDoesNotExist(): void
    {
        $repository = self::createStub(ImageRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ImageNotFound::withId(ImageIdMother::DEFAULT_VALUE));

        (new ImageUnpublisher(ImageIdMother::DEFAULT_VALUE))($repository, self::createStub(EventBus::class), self::createStub(ImageRedirects::class));
    }
}
