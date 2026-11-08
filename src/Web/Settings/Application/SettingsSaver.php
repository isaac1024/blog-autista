<?php

declare(strict_types=1);

namespace Web\Settings\Application;

use Web\Settings\Domain\CmsSettingsPublished;
use Web\Settings\Domain\Settings;
use Web\Settings\Domain\SettingsRepository;

/**
 * Puts on the site the version of the settings the CMS published, in place of
 * the one there, if any.
 */
final readonly class SettingsSaver
{
    public function __construct(private CmsSettingsPublished $event) {}

    public function __invoke(SettingsRepository $settingsRepository): void
    {
        $settings = $settingsRepository->find();

        if (null === $settings) {
            $settings = Settings::publish($this->event);
        } else {
            $settings->republish($this->event);
        }

        $settings->save($settingsRepository);
    }
}
