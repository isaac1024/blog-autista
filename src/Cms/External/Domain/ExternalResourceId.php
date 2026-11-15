<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of an external resource.
 */
final readonly class ExternalResourceId extends Uuid {}
