<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\Event\RevisedDomainEvent;

/**
 * An external resource was taken off the site.
 */
final readonly class ExternalResourceUnpublished extends RevisedDomainEvent
{
    #[\Override]
    public static function eventName(): string
    {
        return 'cms.external.unpublished';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['revision' => $this->revision()];
    }
}
