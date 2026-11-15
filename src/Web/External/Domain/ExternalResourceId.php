<?php

declare(strict_types=1);

namespace Web\External\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of an external resource on the site.
 */
final readonly class ExternalResourceId extends Uuid {}
