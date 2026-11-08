<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Event;

use Cms\Relation\Application\SettingsLinksRelator;
use Cms\Relation\Domain\CmsSettingsPagesLinked;
use Cms\Relation\Domain\RelationRepository;
use Shared\Domain\Event\DomainEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;

/**
 * Keeps the relations of the settings to the pages their menus lead to, as
 * the settings tell them.
 */
final readonly class CmsSettingsPagesLinkedSubscriber implements DomainEventSubscriber
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsSettingsPagesLinked::class];
    }

    #[\Override]
    public function handle(DomainEvent $event): void
    {
        if ($event instanceof CmsSettingsPagesLinked) {
            new SettingsLinksRelator($event)($this->relationRepository);
        }
    }
}
