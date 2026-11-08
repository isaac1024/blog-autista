<?php

declare(strict_types=1);

namespace Web\Settings\Application;

use Web\Settings\Domain\SettingsRepository;

/**
 * The settings on the site; null until the CMS publishes them, when the site
 * paints its pages without them.
 */
final readonly class PublishedSettingsFinder
{
    public function __invoke(SettingsRepository $settingsRepository): ?SettingsResponse
    {
        $settings = $settingsRepository->find();

        return null === $settings ? null : SettingsResponse::fromSettings($settings);
    }
}
