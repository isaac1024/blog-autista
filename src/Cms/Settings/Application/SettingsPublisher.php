<?php

declare(strict_types=1);

namespace Cms\Settings\Application;

use Cms\Settings\Domain\SettingsRepository;
use Shared\Domain\Event\EventBus;

/**
 * Puts on the site the version of the settings last saved.
 */
final readonly class SettingsPublisher
{
    public function __invoke(SettingsRepository $settingsRepository, EventBus $eventBus): void
    {
        $settings = $settingsRepository->find() ?? throw SettingsNotFound::create();

        $settings->publish();
        $settings->save($settingsRepository, $eventBus);
    }
}
