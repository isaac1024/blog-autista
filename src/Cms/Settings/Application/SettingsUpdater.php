<?php

declare(strict_types=1);

namespace Cms\Settings\Application;

use Cms\Settings\Domain\SettingsRepository;
use Shared\Domain\Event\EventBus;

/**
 * Saves what an editor writes in the settings: the name of the blog, its
 * default image and its menus.
 */
final readonly class SettingsUpdater
{
    /**
     * @param string                  $defaultImage id of an image, blank for none
     * @param array<array-key, mixed> $mainMenu     entries with a page_id and an optional label
     * @param array<array-key, mixed> $footerMenu   entries with a page_id and an optional label
     */
    public function __construct(
        private string $name,
        private string $defaultImage,
        private array $mainMenu,
        private array $footerMenu,
    ) {}

    public function __invoke(SettingsRepository $settingsRepository, EventBus $eventBus): void
    {
        $settings = $settingsRepository->find() ?? throw SettingsNotFound::create();

        $settings->edit($this->name, $this->defaultImage, $this->mainMenu, $this->footerMenu);
        $settings->save($settingsRepository, $eventBus);
    }
}
