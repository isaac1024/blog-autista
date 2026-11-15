<?php

declare(strict_types=1);

namespace Web\External\Domain;

use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\External\ImageShape;
use Shared\Domain\External\ResourceKind;

/**
 * An external resource on the site, as the CMS last published it, and
 * whether its address still leads to it: only published resources are
 * here, and only available ones are painted, with the copies of their
 * image the site makes from the file the CMS keeps.
 *
 * Its constructor takes all of its state, one parameter each, as Doctrine maps it.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
#[ORM\Entity]
#[ORM\Table(name: 'web_external_resource')]
final class ExternalResource
{
    /**
     * The image, and its shape, the site served before the version it now
     * takes, whose copies are removed once that version is saved. Not
     * persisted.
     *
     * @var ?array{string, ImageShape}
     */
    private ?array $replacedImage = null;

    /**
     * @param array<array-key, mixed> $details as the CMS published them
     * @param ?string                 $image   where the CMS keeps its image, relative to its storage
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'web_external_resource_id')]
        private readonly ExternalResourceId $id,
        #[ORM\Column(length: 20, enumType: ResourceKind::class)]
        private ResourceKind $kind,
        #[ORM\Column(length: 20, nullable: true)]
        private ?string $provider,
        #[ORM\Column(length: 300, nullable: true)]
        private ?string $locator,
        #[ORM\Column(length: 2000)]
        private string $url,
        #[ORM\Column(length: 200)]
        private string $title,
        #[ORM\Column(length: 300, nullable: true)]
        private ?string $summary,
        #[ORM\Column(length: 5)]
        private string $language,
        #[ORM\Column(type: 'json')]
        private array $details,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $image,
        #[ORM\Column(length: 20, nullable: true, enumType: ImageShape::class)]
        private ?ImageShape $imageShape,
        #[ORM\Column]
        private bool $available,
    ) {}

    public static function publish(CmsExternalResourcePublished $event): self
    {
        return new self(
            new ExternalResourceId($event->aggregateId()),
            $event->kind(),
            $event->provider(),
            $event->locator(),
            $event->url(),
            $event->title(),
            $event->summary(),
            $event->language(),
            $event->details(),
            $event->image(),
            $event->imageShape(),
            $event->available(),
        );
    }

    /**
     * Takes the version the CMS published, in place of the one on the site.
     * A new image names new copies; those of the image replaced go once it is.
     */
    public function republish(CmsExternalResourcePublished $event): void
    {
        if (null !== $this->image && null !== $this->imageShape && $event->image() !== $this->image) {
            $this->replacedImage = [$this->image, $this->imageShape];
        }

        $this->kind = $event->kind();
        $this->provider = $event->provider();
        $this->locator = $event->locator();
        $this->url = $event->url();
        $this->title = $event->title();
        $this->summary = $event->summary();
        $this->language = $event->language();
        $this->details = $event->details();
        $this->image = $event->image();
        $this->imageShape = $event->imageShape();
        $this->available = $event->available();
    }

    /**
     * Whether its address leads to it, as the CMS last checked.
     */
    public function changeAvailability(bool $available): void
    {
        $this->available = $available;
    }

    /**
     * Makes the copies of its image and keeps the resource; once it is saved,
     * the copies of the image replaced are removed.
     */
    public function save(ExternalResourceRepository $resourceRepository, ImageCopies $imageCopies): void
    {
        if (null !== $this->image && null !== $this->imageShape) {
            $imageCopies->publish($this->image, $this->imageShape);
        }

        $resourceRepository->save($this);

        if (null !== $this->replacedImage) {
            $imageCopies->remove(...$this->replacedImage);
        }

        $this->replacedImage = null;
    }

    /**
     * Takes the resource off the site. The copies of its image go first, so
     * the site stops serving them even if removing it fails.
     */
    public function unpublish(ExternalResourceRepository $resourceRepository, ImageCopies $imageCopies): void
    {
        if (null !== $this->image && null !== $this->imageShape) {
            $imageCopies->remove($this->image, $this->imageShape);
        }

        $resourceRepository->delete($this);
    }

    public function id(): ExternalResourceId
    {
        return $this->id;
    }

    public function kind(): ResourceKind
    {
        return $this->kind;
    }

    public function provider(): ?string
    {
        return $this->provider;
    }

    public function locator(): ?string
    {
        return $this->locator;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function summary(): ?string
    {
        return $this->summary;
    }

    public function language(): string
    {
        return $this->language;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }

    public function image(): ?string
    {
        return $this->image;
    }

    public function imageShape(): ?ImageShape
    {
        return $this->imageShape;
    }
}
