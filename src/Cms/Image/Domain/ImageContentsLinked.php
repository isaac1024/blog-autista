<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Shared\Domain\Event\DeliveredImmediately;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\RichText\LinkedContents;

/**
 * The posts, sections and tags, and authors the captions of an image link to changed: those of the caption being edited and of the one last published, together; none once the image is deleted. It is told at once, so
 * whoever keeps track of what is linked is never behind.
 */
final readonly class ImageContentsLinked extends DomainEvent implements DeliveredImmediately
{
    public function __construct(
        string $aggregateId,
        private LinkedContents $linked,
    ) {
        parent::__construct($aggregateId);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.image.contents_linked';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['post_ids' => $this->linked->posts(), 'term_ids' => $this->linked->terms(), 'author_ids' => $this->linked->authors()];
    }
}
