<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

use Shared\Domain\Uuid;

/**
 * The page a visit was to: the id it has on the site, and in the CMS.
 */
final readonly class PageId extends Uuid {}
