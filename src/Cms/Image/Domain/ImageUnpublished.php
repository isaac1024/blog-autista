<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Shared\Domain\Event\RevisedDomainEvent;

/**
 * An image was taken off the site.
 */
final readonly class ImageUnpublished extends RevisedDomainEvent
{
    #[\Override]
    public static function eventName(): string
    {
        return 'cms.image.unpublished';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['revision' => $this->revision()];
    }
}
