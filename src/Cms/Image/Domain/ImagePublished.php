<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Shared\Domain\Event\RevisedDomainEvent;

/**
 * An image was put on the site, or its version there replaced, with all the
 * site needs to serve it: its texts and where its file is kept.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class ImagePublished extends RevisedDomainEvent
{
    /**
     * @param array{version: int, blocks: list<array<string, mixed>>} $caption in the stored rich text format
     * @param string                                                  $path    where its file is kept, relative to the image storage
     */
    public function __construct(
        string $aggregateId,
        private string $alt,
        private array $caption,
        private string $path,
        int $revision,
    ) {
        parent::__construct($aggregateId, $revision);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.image.published';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return [
            'alt' => $this->alt,
            'caption' => $this->caption,
            'path' => $this->path,
            'revision' => $this->revision(),
        ];
    }
}
