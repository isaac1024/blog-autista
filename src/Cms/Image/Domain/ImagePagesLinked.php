<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Shared\Domain\Event\DeliveredImmediately;
use Shared\Domain\Event\DomainEvent;

/**
 * The pages the caption of an image links to changed: those of the version
 * being edited and of the one last published, together; none once the image
 * is deleted. It is told at once, so whoever keeps track of the pages linked
 * is never behind the image.
 */
final readonly class ImagePagesLinked extends DomainEvent implements DeliveredImmediately
{
    /**
     * @param list<string> $pageIds
     */
    public function __construct(
        string $aggregateId,
        private array $pageIds,
    ) {
        parent::__construct($aggregateId);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.image.pages_linked';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['page_ids' => $this->pageIds];
    }
}
