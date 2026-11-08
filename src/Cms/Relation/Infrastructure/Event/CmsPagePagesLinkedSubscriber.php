<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Event;

use Cms\Relation\Application\PageLinksRelator;
use Cms\Relation\Domain\CmsPagePagesLinked;
use Cms\Relation\Domain\RelationRepository;
use Shared\Domain\Event\DomainEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;

/**
 * Keeps the relations of each page to the pages it links to, as the page
 * tells them.
 */
final readonly class CmsPagePagesLinkedSubscriber implements DomainEventSubscriber
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsPagePagesLinked::class];
    }

    #[\Override]
    public function handle(DomainEvent $event): void
    {
        if ($event instanceof CmsPagePagesLinked) {
            new PageLinksRelator($event)($this->relationRepository);
        }
    }
}
