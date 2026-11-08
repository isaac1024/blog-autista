<?php

declare(strict_types=1);

namespace Cms\Settings\Application;

use Cms\Settings\Domain\SettingsRepository;

/**
 * Finds the settings of the site, failing while they don't exist.
 */
final readonly class SettingsFinder
{
    public function __invoke(SettingsRepository $settingsRepository): SettingsResponse
    {
        $settings = $settingsRepository->find() ?? throw SettingsNotFound::create();

        return SettingsResponse::fromSettings($settings);
    }
}
