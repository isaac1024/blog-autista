<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\Event\RevisedDomainEvent;

/**
 * A post was taken off the site.
 */
final readonly class PostUnpublished extends RevisedDomainEvent
{
    #[\Override]
    public static function eventName(): string
    {
        return 'cms.post.unpublished';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return ['revision' => $this->revision()];
    }
}
