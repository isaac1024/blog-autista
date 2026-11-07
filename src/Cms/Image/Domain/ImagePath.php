<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Where the file of an image is kept, relative to the image storage: under
 * the year and the month it was uploaded, so no directory grows without end,
 * and named after the image, which keeps it unique.
 *
 * It is stored rather than worked out, so files already kept stay where they
 * are if the layout changes.
 */
#[ORM\Embeddable]
final readonly class ImagePath
{
    public const int MAX_LENGTH = 64;

    public function __construct(
        #[ORM\Column(name: 'path', length: self::MAX_LENGTH)]
        private string $value,
    ) {}

    public static function for(ImageId $id, \DateTimeImmutable $uploadedAt): self
    {
        return new self(\sprintf('%s/%s.webp', $uploadedAt->format('Y/m'), $id->value()));
    }

    public function value(): string
    {
        return $this->value;
    }
}
