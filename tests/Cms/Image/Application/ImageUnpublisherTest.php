<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Application;

use Cms\Image\Application\ImageNotFound;
use Cms\Image\Application\ImageUnpublisher;
use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageContentsLinked;
use Cms\Image\Domain\ImagePagesLinked;
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

    private const string LINKED_PAGE = '01a1b801-ce58-723d-8234-b76d9105e001';

    private const string LINKED_POST = '01a1b801-ce58-723d-8234-b76d9105f001';

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

    /**
     * While a redirect leads to it, the image stays on the site.
     */
    public function testItKeepsOnTheSiteAnImageARedirectLeadsTo(): void
    {
        $image = ImageMother::published();
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn($image);
        $repository->expects(self::never())->method('save');
        $imageRedirects = $this->createMock(ImageRedirects::class);
        $imageRedirects->expects(self::once())->method('isTargeted')->with(self::equalTo($image->id()))->willReturn(true);

        self::assertTells('errors.image.publication.redirected', [], static fn () => (new ImageUnpublisher(ImageIdMother::DEFAULT_VALUE))($repository, self::createStub(EventBus::class), $imageRedirects));
    }

    public function testItFailsWhenTheImageDoesNotExist(): void
    {
        $repository = self::createStub(ImageRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ImageNotFound::withId(ImageIdMother::DEFAULT_VALUE));

        (new ImageUnpublisher(ImageIdMother::DEFAULT_VALUE))($repository, self::createStub(EventBus::class), self::createStub(ImageRedirects::class));
    }

    /**
     * Off the site, what only the caption once there linked to is free: the
     * pages and posts a draft no longer links to can be deleted.
     */
    public function testItStopsUsingWhatOnlyTheVersionOnTheSiteLinkedTo(): void
    {
        $subject = ImageMother::published(publishedCaption: ['version' => 1, 'blocks' => [['type' => 'paragraph', 'text' => [
            ['text' => 'una página', 'marks' => ['link'], 'page_id' => self::LINKED_PAGE],
            ['text' => ' y un post', 'marks' => ['link'], 'post_id' => self::LINKED_POST],
        ]]]]);
        $told = [];
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->willReturnCallback(static function (DomainEvent ...$events) use (&$told): void {
            $told = array_map(static fn (DomainEvent $event): array => [$event::eventName(), $event->toPrimitives()], $events);
        });
        $repository = self::createStub(ImageRepository::class);
        $repository->method('find')->willReturn($subject);

        (new ImageUnpublisher(ImageIdMother::DEFAULT_VALUE))($repository, $eventBus, self::createStub(ImageRedirects::class));

        self::assertSame([
            [ImagePagesLinked::eventName(), ['page_ids' => []]],
            [ImageContentsLinked::eventName(), ['post_ids' => [], 'term_ids' => [], 'author_ids' => [], 'external_ids' => []]],
            [ImageUnpublished::eventName(), ['revision' => 2]],
        ], $told);
    }
}
