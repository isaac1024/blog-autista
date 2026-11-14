<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Event;

use Cms\Relation\Application\AuthorLinksRelator;
use Cms\Relation\Domain\CmsAuthorPagesLinked;
use Cms\Relation\Domain\RelationRepository;
use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;

/**
 * Keeps the relations of each author to the pages its bio uses, as the
 * author tells them.
 */
final readonly class CmsAuthorPagesLinkedSubscriber implements DomainEventSubscriber
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsAuthorPagesLinked::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsAuthorPagesLinked) {
            new AuthorLinksRelator($event)($this->relationRepository);
        }
    }
}
