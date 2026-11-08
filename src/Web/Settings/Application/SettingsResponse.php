<?php

declare(strict_types=1);

namespace Web\Settings\Application;

use Web\Settings\Domain\Settings;

/**
 * Read model of the settings on the site handed to the adapters, so they
 * don't touch the aggregate.
 */
final readonly class SettingsResponse
{
    /**
     * @param ?string                                      $defaultImageId null for none
     * @param list<array{page_id: string, label: ?string}> $mainMenu
     * @param list<array{page_id: string, label: ?string}> $footerMenu
     */
    public function __construct(
        public string $name,
        public ?string $defaultImageId,
        public array $mainMenu,
        public array $footerMenu,
    ) {}

    public static function fromSettings(Settings $settings): self
    {
        return new self($settings->name(), $settings->defaultImageId(), $settings->mainMenu(), $settings->footerMenu());
    }
}
