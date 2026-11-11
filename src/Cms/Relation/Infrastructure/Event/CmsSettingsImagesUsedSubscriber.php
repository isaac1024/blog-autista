<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Event;

use Cms\Relation\Application\SettingsImagesRelator;
use Cms\Relation\Domain\CmsSettingsImagesUsed;
use Cms\Relation\Domain\RelationRepository;
use Shared\Domain\Event\ReceivedEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;

/**
 * Keeps the relations of the settings to the images they use, as the
 * settings tell them.
 */
final readonly class CmsSettingsImagesUsedSubscriber implements DomainEventSubscriber
{
    public function __construct(private RelationRepository $relationRepository) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsSettingsImagesUsed::class];
    }

    #[\Override]
    public function handle(ReceivedEvent $event): void
    {
        if ($event instanceof CmsSettingsImagesUsed) {
            new SettingsImagesRelator($event)($this->relationRepository);
        }
    }
}
