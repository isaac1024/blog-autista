<?php

declare(strict_types=1);

namespace Web\Shared\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of the aggregate a projection keeps the revision of.
 */
final readonly class AggregateId extends Uuid {}
