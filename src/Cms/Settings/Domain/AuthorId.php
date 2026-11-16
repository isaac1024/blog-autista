<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of an author, as the settings know the one of the blog: they only
 * keep which one, never who it is.
 */
final readonly class AuthorId extends Uuid {}
