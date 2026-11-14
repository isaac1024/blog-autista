<?php

declare(strict_types=1);

namespace Web\Author\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of an author on the site: the one it has in the CMS it comes from.
 */
final readonly class AuthorId extends Uuid {}
