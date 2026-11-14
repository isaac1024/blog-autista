<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Event;

use Cms\Relation\Application\PostImagesRelator;
use Cms\Relation\Domain\CmsPostImagesUsed;
use Cms\Relation\Domain\RelationRepository;
use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;

/**
 * Keeps the relations of each post to the images its content embeds, as the post tells them.
 */
final readonly class CmsPostImagesUsedSubscriber implements DomainEventSubscriber
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsPostImagesUsed::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsPostImagesUsed) {
            new PostImagesRelator($event)($this->relationRepository);
        }
    }
}
