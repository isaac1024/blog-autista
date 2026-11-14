<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of a tag a post is labelled with, a term of the Tag taxonomy.
 */
final readonly class TagId extends Uuid {}
