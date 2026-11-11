<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Application;

use Cms\Image\Application\ImageDeleter;
use Cms\Image\Application\ImageNotFound;
use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImagePagesLinked;
use Cms\Image\Domain\ImagePath;
use Cms\Image\Domain\ImageRepository;
use Cms\Image\Domain\ImageStorage;
use Cms\Image\Domain\ImageUsage;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RichText\RichTextMother as Text;

final class ImageDeleterTest extends TestCase
{
    use DomainErrorAssertions;

    /**
     * The image goes first, then its file: a failure there leaves a file
     * behind, never an image pointing at a missing file.
     */
    #[DataProvider('imagesOffTheSite')]
    public function testItDeletesTheImageAndThenItsFile(Image $image): void
    {
        $steps = [];
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn($image);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($image))
            ->willReturnCallback(static function () use (&$steps): void {
                $steps[] = 'image';
            });
        $storage = $this->createMock(ImageStorage::class);
        $storage->expects(self::once())->method('delete')->with(self::equalTo(new ImagePath('2026/11/'.ImageIdMother::DEFAULT_VALUE.'.webp')))
            ->willReturnCallback(static function () use (&$steps): void {
                $steps[] = 'file';
            });

        (new ImageDeleter(ImageIdMother::DEFAULT_VALUE))($repository, $storage, self::usage(false), self::createStub(EventBus::class));

        self::assertSame(['image', 'file'], $steps);
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
     * An image deleted links to no page any longer, told at once.
     */
    public function testItTellsTheImageLinksToNoPageOnceDeleted(): void
    {
        $id = ImageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(ImageRepository::class);
        $repository->method('find')->willReturn(ImageMother::create(id: $id, caption: Text::stored(['type' => 'paragraph', 'text' => [['text' => 'Contacto', 'marks' => ['link'], 'page_id' => '01a1b801-ce58-723d-8234-b76d9105e001']]])));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof ImagePagesLinked
                && $id === $event->aggregateId()
                && ['page_ids' => []] === $event->toPrimitives(),
        ));

        (new ImageDeleter($id))($repository, self::createStub(ImageStorage::class), self::usage(false), $eventBus);
    }

    public function testItKeepsTheFileWhenTheImageCannotBeDeleted(): void
    {
        $failure = new \RuntimeException('The database is down.');
        $repository = self::createStub(ImageRepository::class);
        $repository->method('find')->willReturn(ImageMother::create());
        $repository->method('delete')->willThrowException($failure);
        $storage = $this->createMock(ImageStorage::class);
        $storage->expects(self::never())->method('delete');

        $this->expectExceptionObject($failure);

        (new ImageDeleter(ImageIdMother::DEFAULT_VALUE))($repository, $storage, self::usage(false), self::createStub(EventBus::class));
    }

    public function testItDeletesNothingWhileTheImageIsOnTheSite(): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn(ImageMother::published());
        $repository->expects(self::never())->method('delete');
        $storage = $this->createMock(ImageStorage::class);
        $storage->expects(self::never())->method('delete');

        self::assertTells('errors.image.publication.published_image', [], static fn () => (new ImageDeleter(ImageIdMother::DEFAULT_VALUE))($repository, $storage, self::usage(false), self::createStub(EventBus::class)));
    }

    /**
     * An image a page embeds can be unpublished, never deleted.
     */
    public function testItDeletesNothingWhileTheImageIsInUse(): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn(ImageMother::unpublished());
        $repository->expects(self::never())->method('delete');
        $storage = $this->createMock(ImageStorage::class);
        $storage->expects(self::never())->method('delete');
        $usage = $this->createMock(ImageUsage::class);
        $usage->expects(self::once())->method('isInUse')->with(self::equalTo(ImageIdMother::create()))->willReturn(true);

        self::assertTells('errors.image.publication.in_use', [], static fn () => (new ImageDeleter(ImageIdMother::DEFAULT_VALUE))($repository, $storage, $usage, self::createStub(EventBus::class)));
    }

    public function testItFailsWhenTheImageDoesNotExist(): void
    {
        $repository = self::createStub(ImageRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ImageNotFound::withId(ImageIdMother::DEFAULT_VALUE));

        (new ImageDeleter(ImageIdMother::DEFAULT_VALUE))($repository, self::createStub(ImageStorage::class), self::usage(false), self::createStub(EventBus::class));
    }

    private static function usage(bool $inUse): ImageUsage
    {
        $usage = self::createStub(ImageUsage::class);
        $usage->method('isInUse')->willReturn($inUse);

        return $usage;
    }
}
