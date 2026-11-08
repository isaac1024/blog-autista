<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Doctrine\ORM\Mapping as ORM;
use Shared\Domain\RichText\RichText;

/**
 * What an editor writes about an image: its alternative text and its
 * caption. The one being edited and the one on the site are both versions,
 * so the site keeps its version while the image is edited.
 */
#[ORM\Embeddable]
final readonly class ImageVersion
{
    public function __construct(
        #[ORM\Embedded(class: Alt::class, columnPrefix: false)]
        private Alt $alt,
        /*
         * Its caption, as checked by Caption; null while it has none.
         */
        #[ORM\Column(name: 'caption', type: 'rich_text', nullable: true)]
        private ?RichText $caption,
    ) {}

    /**
     * A version from what an editor writes, the caption in the stored rich
     * text format; an empty one leaves it without a caption.
     *
     * @param array<array-key, mixed> $caption
     */
    public static function fromPrimitives(string $alt, array $caption): self
    {
        return new self(new Alt($alt), Caption::fromStored($caption)?->text());
    }

    /**
     * Whether it has everything the site shows of an image: the alternative
     * text always has a value, the caption has to be written.
     */
    public function assertPublishable(): void
    {
        if (null === $this->caption) {
            throw InvalidImagePublication::withoutCaption();
        }
    }

    public function equals(self $other): bool
    {
        return $this->alt->value() === $other->alt->value()
            && $this->caption?->toArray() === $other->caption?->toArray();
    }

    public function alt(): Alt
    {
        return $this->alt;
    }

    public function caption(): ?RichText
    {
        return $this->caption;
    }
}
