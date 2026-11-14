<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of an author.
 */
final readonly class AuthorId extends Uuid {}
