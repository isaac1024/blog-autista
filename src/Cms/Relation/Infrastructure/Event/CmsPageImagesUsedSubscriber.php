<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Event;

use Cms\Relation\Application\PageImagesRelator;
use Cms\Relation\Domain\CmsPageImagesUsed;
use Cms\Relation\Domain\RelationRepository;
use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;

/**
 * Keeps the relations of each page to the images it embeds, as the page
 * tells them.
 */
final readonly class CmsPageImagesUsedSubscriber implements DomainEventSubscriber
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsPageImagesUsed::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsPageImagesUsed) {
            new PageImagesRelator($event)($this->relationRepository);
        }
    }
}
