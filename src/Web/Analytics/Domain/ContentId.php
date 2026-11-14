<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

use Shared\Domain\Uuid;

/**
 * The id of what a visit was to, on the site and in the CMS.
 */
final readonly class ContentId extends Uuid {}
