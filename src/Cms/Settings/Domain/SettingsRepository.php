<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

/**
 * Outbound port used to persist and read the settings of the site.
 */
interface SettingsRepository
{
    public function save(Settings $settings): void;

    /**
     * The settings of the site, null until they are created.
     */
    public function find(): ?Settings;
}
