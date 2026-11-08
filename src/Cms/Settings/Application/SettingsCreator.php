<?php

declare(strict_types=1);

namespace Cms\Settings\Application;

use Cms\Settings\Domain\Settings;
use Cms\Settings\Domain\SettingsAlreadyExist;
use Cms\Settings\Domain\SettingsRepository;
use Shared\Domain\Event\EventBus;

/**
 * Creates the settings of the site, failing when they already exist.
 */
final readonly class SettingsCreator
{
    public function __construct(
        private string $id,
        private string $name,
    ) {}

    public function __invoke(SettingsRepository $settingsRepository, EventBus $eventBus): void
    {
        $settings = Settings::create($this->id, $this->name);

        if (null !== $settingsRepository->find()) {
            throw SettingsAlreadyExist::create();
        }

        $settings->save($settingsRepository, $eventBus);
    }
}
