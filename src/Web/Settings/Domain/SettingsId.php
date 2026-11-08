<?php

declare(strict_types=1);

namespace Web\Settings\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of the settings on the site, the one they have in the CMS.
 */
final readonly class SettingsId extends Uuid {}
