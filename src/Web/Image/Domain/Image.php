<?php

declare(strict_types=1);

namespace Web\Image\Domain;

use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\RichText\RichText;

/**
 * An image on the site: the version of it the CMS published, with the copies
 * the site serves. It only exists while the image is published, so the site
 * never shows one taken off.
 *
 * Events can come late or twice: the projection that hands them over only
 * lets through those newer than the last it applied, by their revision.
 */
#[ORM\Entity]
#[ORM\Table(name: 'web_image')]
final class Image
{
    /**
     * @param string $path where the CMS keeps its file, relative to its image storage, which names its copies
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'web_image_id')]
        private readonly ImageId $id,
        #[ORM\Column(length: 125)]
        private string $alt,
        #[ORM\Column(type: 'rich_text')]
        private RichText $caption,
        #[ORM\Column(length: 64)]
        private readonly string $path,
    ) {}

    public static function publish(CmsImagePublished $event): self
    {
        return new self(new ImageId($event->aggregateId()), $event->alt(), $event->caption(), $event->path());
    }

    /**
     * Takes the version the CMS published. Its file never changes, so
     * neither do its copies.
     */
    public function republish(CmsImagePublished $event): void
    {
        $this->alt = $event->alt();
        $this->caption = $event->caption();
    }

    /**
     * Makes its copies and saves the image, both or neither: when saving
     * fails, the copies made are removed, so the site never serves copies of
     * an image it doesn't have.
     */
    public function save(ImageRepository $imageRepository, ImageVariants $imageVariants): void
    {
        $imageVariants->publish($this->path);

        try {
            $imageRepository->save($this);
        } catch (\Throwable $exception) {
            $imageVariants->remove($this->path);

            throw $exception;
        }
    }

    /**
     * Takes the image off the site. Its copies go first, so the site stops
     * serving them even if removing the image fails.
     */
    public function unpublish(ImageRepository $imageRepository, ImageVariants $imageVariants): void
    {
        $imageVariants->remove($this->path);
        $imageRepository->delete($this);
    }

    public function id(): ImageId
    {
        return $this->id;
    }

    public function alt(): string
    {
        return $this->alt;
    }

    public function caption(): RichText
    {
        return $this->caption;
    }

    public function path(): string
    {
        return $this->path;
    }
}
