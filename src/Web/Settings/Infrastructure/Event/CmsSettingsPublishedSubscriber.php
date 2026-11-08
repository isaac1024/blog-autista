<?php

declare(strict_types=1);

namespace Web\Settings\Infrastructure\Event;

use Shared\Domain\Event\DomainEvent;
use Shared\Infrastructure\Event\DomainEventSubscriber;
use Web\Settings\Application\SettingsSaver;
use Web\Settings\Domain\CmsSettingsPublished;
use Web\Settings\Domain\SettingsRepository;

/**
 * Puts on the site each version of the settings the CMS publishes.
 */
final readonly class CmsSettingsPublishedSubscriber implements DomainEventSubscriber
{
    public function __construct(private SettingsRepository $settingsRepository) {}

    #[\Override]
    public static function subscribedTo(): array
    {
        return [CmsSettingsPublished::class];
    }

    #[\Override]
    public function handle(DomainEvent $event): void
    {
        if ($event instanceof CmsSettingsPublished) {
            new SettingsSaver($event)($this->settingsRepository);
        }
    }
}
