<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\Event\RevisedDomainEvent;

/**
 * A page was taken off the site.
 */
final readonly class PageUnpublished extends RevisedDomainEvent
{
    #[\Override]
    public static function eventName(): string
    {
        return 'cms.page.unpublished';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['revision' => $this->revision()];
    }
}
