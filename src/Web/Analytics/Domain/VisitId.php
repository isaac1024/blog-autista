<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of a visit, while it waits to be counted.
 */
final readonly class VisitId extends Uuid {}
