<?php

declare(strict_types=1);

namespace Cms\Image\Application;

use Cms\Image\Domain\Image;

/**
 * An image as the CMS shows it.
 *
 * It carries everything an editor shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class ImageResponse
{
    /**
     * @param ?array{version: int, blocks: list<array<string, mixed>>} $caption               in the stored rich text format; null while it has none
     * @param string                                                   $path                  where its file is kept, relative to the image storage
     * @param string                                                   $status                one of the ImageStatus values
     * @param bool                                                     $changed               whether it is published with changes not on the site yet, which can be discarded
     * @param bool                                                     $hasSomethingToPublish whether publishing would change the site
     * @param bool                                                     $canBeUnpublished      whether it can be taken off the site
     * @param bool                                                     $canBeDeleted          whether it can be deleted
     */
    public function __construct(
        public string $id,
        public string $alt,
        public ?array $caption,
        public string $path,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public string $status,
        public bool $changed,
        public bool $hasSomethingToPublish,
        public bool $canBeUnpublished,
        public bool $canBeDeleted,
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
            $image->status()->value,
            $image->hasUnpublishedChanges(),
            $image->hasSomethingToPublish(),
            $image->canBeUnpublished(),
            $image->canBeDeleted(),
        );
    }
}
