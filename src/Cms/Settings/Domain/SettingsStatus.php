<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

/**
 * Whether the settings are on the site. A draft has never been published;
 * once published, they stay on it.
 */
enum SettingsStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
