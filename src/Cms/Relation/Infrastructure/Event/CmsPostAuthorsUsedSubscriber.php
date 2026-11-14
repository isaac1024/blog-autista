<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Event;

use Cms\Relation\Application\PostAuthorsRelator;
use Cms\Relation\Domain\CmsPostAuthorsUsed;
use Cms\Relation\Domain\RelationRepository;
use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;

/**
 * Keeps the relations of each post to the authors it is signed by, as the post tells them.
 */
final readonly class CmsPostAuthorsUsedSubscriber implements DomainEventSubscriber
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsPostAuthorsUsed::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsPostAuthorsUsed) {
            new PostAuthorsRelator($event)($this->relationRepository);
        }
    }
}
