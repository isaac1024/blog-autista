<?php

declare(strict_types=1);

namespace Cms\Settings\Application;

use Cms\Settings\Domain\SettingsRepository;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;

/**
 * Saves what an editor writes in the settings: the name of the blog, its
 * default image, its author and its menus.
 */
final readonly class SettingsUpdater
{
    /**
     * @param string                  $defaultImage id of an image, blank for none
     * @param string                  $author       id of an author, blank for none
     * @param array<array-key, mixed> $mainMenu     entries with a page_id and an optional label
     * @param array<array-key, mixed> $footerMenu   entries with a page_id and an optional label
     */
    public function __construct(
        private string $name,
        private string $defaultImage,
        private string $author,
        private array $mainMenu,
        private array $footerMenu,
    ) {}

    public function __invoke(SettingsRepository $settingsRepository, EventBus $eventBus, Transaction $transaction): void
    {
        $settings = $settingsRepository->find() ?? throw SettingsNotFound::create();

        $settings->edit($this->name, $this->defaultImage, $this->author, $this->mainMenu, $this->footerMenu);
        $settings->save($settingsRepository, $eventBus, $transaction);
    }
}
