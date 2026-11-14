<?php

declare(strict_types=1);

namespace Web\Post\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of a post on the site, the one the CMS gave it.
 */
final readonly class PostId extends Uuid {}
