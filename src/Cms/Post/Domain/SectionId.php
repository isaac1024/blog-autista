<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of the section a post is filed under, a term of the Section taxonomy.
 */
final readonly class SectionId extends Uuid {}
