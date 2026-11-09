<?php

declare(strict_types=1);

namespace Web\Redirect\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of a redirect on the site: the one it has in the CMS it comes from.
 */
final readonly class RedirectId extends Uuid {}
