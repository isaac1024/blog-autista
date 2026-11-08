<?php

declare(strict_types=1);

namespace Web\Settings\Domain;

/**
 * Outbound port used to persist and read the settings on the site.
 */
interface SettingsRepository
{
    public function save(Settings $settings): void;

    /**
     * The settings on the site, null until the CMS publishes them.
     */
    public function find(): ?Settings;
}
