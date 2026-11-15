<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Event;

use Cms\Relation\Application\ImageContentsRelator;
use Cms\Relation\Domain\CmsImageContentsLinked;
use Cms\Relation\Domain\RelationRepository;
use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;

/**
 * Keeps the relations of each image to the posts, sections and tags, and
 * authors it links to, as the image tells them.
 */
final readonly class CmsImageContentsLinkedSubscriber implements DomainEventSubscriber
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsImageContentsLinked::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsImageContentsLinked) {
            new ImageContentsRelator($event)($this->relationRepository);
        }
    }
}
