<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\AggregateRoot;
use Shared\Domain\Clock;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Event\EventId;
use Shared\Domain\RichText\RichText;

/**
 * An image of the library: a 16:9 crop, 1200 by 675 pixels, as WebP, cropped
 * and encoded by the browser before it is uploaded, with the text that
 * describes it.
 *
 * Its file is kept apart, in the image storage, at its path, and never
 * changes: a new crop is a new image. An image and its file are saved, and
 * deleted, together.
 *
 * Editors work on its version in the CMS; once published, the site serves a
 * copy of it, which only changes when the image is published again.
 *
 * - Editing saves whatever valid texts an editor writes, caption or not.
 * - An image is published only with a caption.
 * - A published image is unpublished before it is deleted, and an image in
 *   use is never deleted; it can be unpublished, and the site leaves it out.
 *
 * Its rules reach every value object, error and event of the image, so its
 * coupling grows with what an image is rather than with how it is designed.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
#[ORM\Entity]
#[ORM\Table(name: 'cms_image')]
#[ORM\Index(name: 'ftx_cms_image_alt', columns: ['alt'], flags: ['fulltext'])]
final class Image extends AggregateRoot
{
    /**
     * Size of every image, in pixels.
     */
    public const int WIDTH = 1200;

    public const int HEIGHT = 675;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'cms_image_id')]
        private readonly ImageId $id,
        #[ORM\Embedded(class: ImageVersion::class, columnPrefix: false)]
        private ImageVersion $version,
        #[ORM\Embedded(class: ImagePath::class, columnPrefix: false)]
        private readonly ImagePath $path,
        #[ORM\Column(length: 20, enumType: ImageStatus::class)]
        private ImageStatus $status,
        /*
         * The version last published, kept when the image is unpublished.
         */
        #[ORM\Column(name: 'published_version', type: 'cms_image_version', nullable: true)]
        private ?ImageVersion $published,
        #[ORM\Embedded(class: Timeline::class, columnPrefix: false)]
        private Timeline $timeline,
    ) {}

    /**
     * Initialises a new image from primitives, kept under the month it is
     * created, as a draft without a caption.
     */
    public static function create(string $id, string $alt): self
    {
        $timeline = Timeline::start(Clock::now());
        $imageId = new ImageId($id);

        return new self(
            $imageId,
            new ImageVersion(new Alt($alt), null),
            ImagePath::for($imageId, $timeline->createdAt()),
            ImageStatus::Draft,
            null,
            $timeline,
        );
    }

    /**
     * Replaces what an editor writes: the alternative text and the caption,
     * which may be left empty until the image is published.
     *
     * On a published image, it only changes the version being edited: the
     * site keeps its version until the image is published again.
     *
     * @param array<array-key, mixed> $caption in the stored rich text format
     */
    public function edit(string $alt, array $caption): void
    {
        $this->replaceVersion(ImageVersion::fromPrimitives($alt, $caption));
    }

    /**
     * Puts the version last saved on the site, for the first time or in place
     * of the one there, once it has a caption.
     */
    public function publish(): void
    {
        $this->version->assertPublishable();

        if (!$this->hasSomethingToPublish()) {
            throw InvalidImagePublication::nothingToPublish();
        }

        $this->status = ImageStatus::Published;
        $this->published = $this->version;

        $this->record(new ImagePublished(
            $this->id->value(),
            $this->version->alt()->value(),
            // A publishable version has a caption.
            $this->version->caption()?->toArray() ?? RichText::empty()->toArray(),
            $this->path->value(),
            EventId::random()->value(),
            Clock::now(),
        ));
    }

    /**
     * Takes the image off the site.
     */
    public function unpublish(): void
    {
        if (ImageStatus::Published !== $this->status) {
            throw InvalidImagePublication::notPublished();
        }

        $this->status = ImageStatus::Unpublished;

        $this->record(new ImageUnpublished($this->id->value(), EventId::random()->value(), Clock::now()));
    }

    /**
     * Brings the version being edited back to the one on the site.
     */
    public function discardChanges(): void
    {
        if (null === $this->published || !$this->hasUnpublishedChanges()) {
            throw InvalidImagePublication::noChangesToDiscard();
        }

        $this->replaceVersion($this->published);
    }

    /**
     * Whether the image is on the site with a version other than the one
     * being edited, so its changes can be discarded.
     */
    public function hasUnpublishedChanges(): bool
    {
        return ImageStatus::Published === $this->status
            && null !== $this->published
            && !$this->published->equals($this->version);
    }

    /**
     * Whether publishing has something to put on the site: the image isn't
     * there, or it is with another version.
     */
    public function hasSomethingToPublish(): bool
    {
        return ImageStatus::Published !== $this->status || $this->hasUnpublishedChanges();
    }

    /**
     * Whether it can be taken off the site: it is on it.
     */
    public function canBeUnpublished(): bool
    {
        return ImageStatus::Published === $this->status;
    }

    /**
     * Whether it can be deleted: it is off the site.
     */
    public function canBeDeleted(): bool
    {
        return ImageStatus::Published !== $this->status;
    }

    /**
     * Keeps its file and saves the image, both or neither. The file goes
     * first, so a failure there saves nothing; when saving the image fails,
     * the file kept is removed. Only a process dying between both could leave
     * a file behind, never an image pointing at a missing file.
     */
    public function upload(ImageFile $file, ImageStorage $imageStorage, ImageRepository $imageRepository, EventBus $eventBus): void
    {
        $imageStorage->save($this->path, $file);

        try {
            $this->save($imageRepository, $eventBus);
        } catch (\Throwable $exception) {
            $imageStorage->delete($this->path);

            throw $exception;
        }
    }

    /**
     * Saves the image, then tells the rest of the system what happened to it
     * since it was loaded. It takes its events before saving, so the saved
     * image holds none, and none is told twice.
     */
    public function save(ImageRepository $imageRepository, EventBus $eventBus): void
    {
        $events = $this->pullDomainEvents();
        $imageRepository->save($this);
        $eventBus->publish(...$events);
    }

    /**
     * Deletes an image off the site that nothing uses, and then its file: a
     * failure there could leave a file behind, never an image pointing at a
     * missing file.
     */
    public function delete(ImageRepository $imageRepository, ImageStorage $imageStorage, ImageUsage $imageUsage): void
    {
        if (ImageStatus::Published === $this->status) {
            throw InvalidImagePublication::publishedImage();
        }

        if ($imageUsage->isInUse($this->id)) {
            throw InvalidImagePublication::inUse();
        }

        $imageRepository->delete($this);
        $imageStorage->delete($this->path);
    }

    public function id(): ImageId
    {
        return $this->id;
    }

    public function alt(): Alt
    {
        return $this->version->alt();
    }

    public function caption(): ?RichText
    {
        return $this->version->caption();
    }

    public function path(): ImagePath
    {
        return $this->path;
    }

    public function status(): ImageStatus
    {
        return $this->status;
    }

    public function timeline(): Timeline
    {
        return $this->timeline;
    }

    /**
     * The update date only moves when what is written changes.
     */
    private function replaceVersion(ImageVersion $version): void
    {
        if (!$version->equals($this->version)) {
            $this->timeline = $this->timeline->updated(Clock::now());
        }

        $this->version = $version;
    }
}
