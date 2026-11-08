<?php

declare(strict_types=1);

namespace Cms\Settings\Application;

use Cms\Settings\Domain\SettingsRepository;

/**
 * Finds the settings as the site shows them, null while they don't exist or
 * aren't published yet.
 */
final readonly class SettingsOnSiteFinder
{
    public function __invoke(SettingsRepository $settingsRepository): ?SettingsOnSiteResponse
    {
        $version = $settingsRepository->find()?->onSite();

        return null === $version ? null : SettingsOnSiteResponse::fromVersion($version);
    }
}
