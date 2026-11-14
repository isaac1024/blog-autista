<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\Uuid;

/**
 * Identity of a post.
 */
final readonly class PostId extends Uuid {}
