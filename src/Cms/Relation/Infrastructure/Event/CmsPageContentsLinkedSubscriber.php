<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Event;

use Cms\Relation\Application\PageContentsRelator;
use Cms\Relation\Domain\CmsPageContentsLinked;
use Cms\Relation\Domain\RelationRepository;
use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;

/**
 * Keeps the relations of each page to the posts, sections and tags, and
 * authors it links to, as the page tells them.
 */
final readonly class CmsPageContentsLinkedSubscriber implements DomainEventSubscriber
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsPageContentsLinked::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsPageContentsLinked) {
            new PageContentsRelator($event)($this->relationRepository);
        }
    }
}
