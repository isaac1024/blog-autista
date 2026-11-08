<?php

declare(strict_types=1);

namespace Cms\Settings\Application;

use Cms\Settings\Domain\SettingsRepository;
use Shared\Domain\Event\EventBus;

/**
 * Brings the version of the settings being edited back to the one on the
 * site.
 */
final readonly class SettingsChangesDiscarder
{
    public function __invoke(SettingsRepository $settingsRepository, EventBus $eventBus): void
    {
        $settings = $settingsRepository->find() ?? throw SettingsNotFound::create();

        $settings->discardChanges();
        $settings->save($settingsRepository, $eventBus);
    }
}
