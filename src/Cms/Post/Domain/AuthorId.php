<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of the author a post is signed by.
 */
final readonly class AuthorId extends Uuid {}
