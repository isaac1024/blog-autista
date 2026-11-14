<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

use Shared\Domain\Event\RevisedDomainEvent;

/**
 * An author was taken off the site.
 */
final readonly class AuthorUnpublished extends RevisedDomainEvent
{
    #[\Override]
    public static function eventName(): string
    {
        return 'cms.author.unpublished';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['revision' => $this->revision()];
    }
}
