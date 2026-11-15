<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Event;

use Cms\Relation\Application\SettingsContentsRelator;
use Cms\Relation\Domain\CmsSettingsContentsLinked;
use Cms\Relation\Domain\RelationRepository;
use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;

/**
 * Keeps the relations of the settings to the posts, sections and tags,
 * authors and external resources their menus lead to, as they tell them.
 */
final readonly class CmsSettingsContentsLinkedSubscriber implements DomainEventSubscriber
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsSettingsContentsLinked::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsSettingsContentsLinked) {
            new SettingsContentsRelator($event)($this->relationRepository);
        }
    }
}
