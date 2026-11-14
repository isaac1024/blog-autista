<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Event;

use Cms\Relation\Application\PostLinksRelator;
use Cms\Relation\Domain\CmsPostPagesLinked;
use Cms\Relation\Domain\RelationRepository;
use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;

/**
 * Keeps the relations of each post to the pages its content links to, as the post tells them.
 */
final readonly class CmsPostPagesLinkedSubscriber implements DomainEventSubscriber
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsPostPagesLinked::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsPostPagesLinked) {
            new PostLinksRelator($event)($this->relationRepository);
        }
    }
}
