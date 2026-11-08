<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of the settings of the site.
 */
final readonly class SettingsId extends Uuid {}
