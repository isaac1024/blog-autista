<?php

declare(strict_types=1);

namespace Cms\Image\Application;

use Cms\Image\Domain\Image;

/**
 * An image as the CMS shows it.
 */
final readonly class ImageResponse
{
    /**
     * @param ?array{version: int, blocks: list<array<string, mixed>>} $caption in the stored rich text format; null while it has none
     * @param string                                                   $path    where its file is kept, relative to the image storage
     */
    public function __construct(
        public string $id,
        public string $alt,
        public ?array $caption,
        public string $path,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {}

    public static function fromImage(Image $image): self
    {
        return new self(
            $image->id()->value(),
            $image->alt()->value(),
            $image->caption()?->toArray(),
            $image->path()->value(),
            $image->timeline()->createdAt(),
            $image->timeline()->updatedAt(),
        );
    }
}
