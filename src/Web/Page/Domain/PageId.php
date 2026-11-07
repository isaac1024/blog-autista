<?php

declare(strict_types=1);

namespace Web\Page\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of a page on the site: the one it has in the CMS it comes from.
 */
final readonly class PageId extends Uuid {}
