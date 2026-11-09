<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

use Shared\Domain\Uuid;

/**
 * The id of a resource of any kind, which a redirect leads to or comes from.
 */
final readonly class ResourceId extends Uuid {}
