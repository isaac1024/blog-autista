<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

use Shared\Domain\Event\RevisedDomainEvent;

/**
 * A term was taken off the site.
 */
final readonly class TermUnpublished extends RevisedDomainEvent
{
    #[\Override]
    public static function eventName(): string
    {
        return 'cms.term.unpublished';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['revision' => $this->revision()];
    }
}
