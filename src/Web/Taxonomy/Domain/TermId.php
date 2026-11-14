<?php

declare(strict_types=1);

namespace Web\Taxonomy\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of a term on the site: the one it has in the CMS it comes from.
 */
final readonly class TermId extends Uuid {}
