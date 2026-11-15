<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Event;

use Cms\Relation\Application\AuthorContentsRelator;
use Cms\Relation\Domain\CmsAuthorContentsLinked;
use Cms\Relation\Domain\RelationRepository;
use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;

/**
 * Keeps the relations of each author to the posts, sections and tags, and
 * authors it links to, as the author tells them.
 */
final readonly class CmsAuthorContentsLinkedSubscriber implements DomainEventSubscriber
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsAuthorContentsLinked::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsAuthorContentsLinked) {
            new AuthorContentsRelator($event)($this->relationRepository);
        }
    }
}
