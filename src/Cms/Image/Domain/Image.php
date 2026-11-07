<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\AggregateRoot;
use Shared\Domain\Clock;
use Shared\Domain\RichText\RichText;

/**
 * An image of the library: a 16:9 crop, 1200 by 675 pixels, as WebP, cropped
 * and encoded by the browser before it is uploaded, with the text that
 * describes it.
 *
 * Its file is kept apart, in the image storage, at its path. An image and its
 * file are saved together or not at all.
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
        #[ORM\Embedded(class: Alt::class, columnPrefix: false)]
        private Alt $alt,
        #[ORM\Embedded(class: ImagePath::class, columnPrefix: false)]
        private readonly ImagePath $path,
        /*
         * Its caption, kept as checked by Caption; null while it has none.
         */
        #[ORM\Column(name: 'caption', type: 'rich_text', nullable: true)]
        private ?RichText $caption,
        #[ORM\Embedded(class: Timeline::class, columnPrefix: false)]
        private Timeline $timeline,
    ) {}

    /**
     * Initialises a new image from primitives, kept under the month it is
     * created.
     */
    public static function create(string $id, string $alt): self
    {
        $timeline = Timeline::start(Clock::now());
        $imageId = new ImageId($id);

        return new self($imageId, new Alt($alt), ImagePath::for($imageId, $timeline->createdAt()), null, $timeline);
    }

    /**
     * Replaces what an editor writes: the alternative text and the caption,
     * which may be left empty until the image is published. Its file never
     * changes: a new crop is a new image.
     *
     * @param array<array-key, mixed> $caption in the stored rich text format
     */
    public function edit(string $alt, array $caption): void
    {
        // Everything is validated before anything is assigned, so an invalid
        // field doesn't leave the image with only part of the edit.
        $newAlt = new Alt($alt);
        $newCaption = Caption::fromStored($caption)?->text();

        // The update date only moves when what is written changes.
        if ($newAlt->value() === $this->alt->value() && $newCaption?->toArray() === $this->caption?->toArray()) {
            return;
        }

        $this->alt = $newAlt;
        $this->caption = $newCaption;
        $this->timeline = $this->timeline->updated(Clock::now());
    }

    /**
     * Keeps its file and saves the image, both or neither. The file goes
     * first, so a failure there saves nothing; when saving the image fails,
     * the file kept is removed. Only a process dying between both could leave
     * a file behind, never an image pointing at a missing file.
     */
    public function upload(ImageFile $file, ImageStorage $imageStorage, ImageRepository $imageRepository): void
    {
        $imageStorage->save($this->path, $file);

        try {
            $this->save($imageRepository);
        } catch (\Throwable $exception) {
            $imageStorage->delete($this->path);

            throw $exception;
        }
    }

    public function save(ImageRepository $imageRepository): void
    {
        $imageRepository->save($this);
    }

    public function id(): ImageId
    {
        return $this->id;
    }

    public function alt(): Alt
    {
        return $this->alt;
    }

    public function caption(): ?RichText
    {
        return $this->caption;
    }

    public function path(): ImagePath
    {
        return $this->path;
    }

    public function timeline(): Timeline
    {
        return $this->timeline;
    }
}
