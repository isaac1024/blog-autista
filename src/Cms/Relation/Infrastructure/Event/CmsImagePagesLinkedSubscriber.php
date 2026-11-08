<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Event;

use Cms\Relation\Application\ImageLinksRelator;
use Cms\Relation\Domain\CmsImagePagesLinked;
use Cms\Relation\Domain\RelationRepository;
use Shared\Domain\Event\DomainEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;

/**
 * Keeps the relations of each image to the pages its caption links to, as
 * the image tells them.
 */
final readonly class CmsImagePagesLinkedSubscriber implements DomainEventSubscriber
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsImagePagesLinked::class];
    }

    #[\Override]
    public function handle(DomainEvent $event): void
    {
        if ($event instanceof CmsImagePagesLinked) {
            new ImageLinksRelator($event)($this->relationRepository);
        }
    }
}
